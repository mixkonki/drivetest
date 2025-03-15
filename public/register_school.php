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
    $school_name = trim($_POST['school_name']);
    $email = trim($_POST['email']);
    $tax_id = trim($_POST['tax_id']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Έλεγχος για κενά υποχρεωτικά πεδία
    if (empty($school_name) || empty($email) || empty($tax_id) || empty($password) || empty($confirm_password)) {
        $error = "Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία!";
    } 
    // Έλεγχος αν οι κωδικοί ταιριάζουν
    elseif ($password !== $confirm_password) {
        $error = "Οι κωδικοί δεν ταιριάζουν!";
    } 
    // Έλεγχος για την πολυπλοκότητα του κωδικού
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/', $password)) {
        $error = "Ο κωδικός πρέπει να περιέχει τουλάχιστον 8 χαρακτήρες, ένα κεφαλαίο γράμμα, έναν αριθμό και έναν ειδικό χαρακτήρα!";
    } 
    else {
        // Έλεγχος αν υπάρχει ήδη το email
        $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error = "Το email είναι ήδη εγγεγραμμένο!";
        } 
        // Έλεγχος για το ΑΦΜ
        else {
            $check_tax_stmt = $mysqli->prepare("SELECT id FROM schools WHERE tax_id = ?");
            $check_tax_stmt->bind_param("s", $tax_id);
            $check_tax_stmt->execute();
            $check_tax_stmt->store_result();
            
            if ($check_tax_stmt->num_rows > 0) {
                $error = "Το ΑΦΜ είναι ήδη καταχωρημένο!";
                $check_tax_stmt->close();
            } else {
                $check_tax_stmt->close();
                
                // Κωδικοποίηση του κωδικού πρόσβασης
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Δημιουργία token επαλήθευσης
                $verification_token = bin2hex(random_bytes(32));
                
                // Χρήση transaction για την εγγραφή σχολής
                $mysqli->begin_transaction();
                
                try {
                    // Εισαγωγή χρήστη
                    $user_stmt = $mysqli->prepare("INSERT INTO users (fullname, email, password, role, verification_token) 
                                                  VALUES (?, ?, ?, 'school', ?)");
                    $user_stmt->bind_param("ssss", $school_name, $email, $hashed_password, $verification_token);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Σφάλμα κατά την εγγραφή χρήστη: " . $user_stmt->error);
                    }
                    
                    // Λήψη του ID του νέου χρήστη
                    $user_id = $mysqli->insert_id;
                    
                    // Εισαγωγή σχολής με βασικά στοιχεία
                    $school_stmt = $mysqli->prepare("INSERT INTO schools (name, email, tax_id) VALUES (?, ?, ?)");
                    $school_stmt->bind_param("sss", $school_name, $email, $tax_id);
                    
                    if (!$school_stmt->execute()) {
                        throw new Exception("Σφάλμα κατά την εγγραφή σχολής: " . $school_stmt->error);
                    }
                    
                    // Αποστολή email επαλήθευσης
                    send_verification_email($email, $verification_token, $language);
                    
                    $mysqli->commit();
                    $success = "Η εγγραφή ολοκληρώθηκε επιτυχώς! Παρακαλώ ελέγξτε το email σας για την επαλήθευση λογαριασμού.";
                    
                    // Ανακατεύθυνση στη σελίδα επιβεβαίωσης
                    header("Location: " . BASE_URL . "/public/email_verification_notice.php?email=" . urlencode($email));
                    exit();
                    
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $error = "Σφάλμα κατά την εγγραφή: " . $e->getMessage();
                }
                
                $user_stmt->close();
                $school_stmt->close();
            }
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($translations['school_register_title']) ? $translations['school_register_title'] : 'Εγγραφή Σχολής - DriveTest' ?></title>
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">
</head>
<body>
    <div class="container">
        <div class="columns-wrapper" style="display: flex; gap: 50px;">
            <div class="info-box">
                <div>
                    <h2>Πλεονεκτήματα Εγγραφής Σχολής</h2>
                    <ul>
                        <li>✓ Διαχείριση μαθητών και παρακολούθηση της προόδου τους</li>
                        <li>✓ Εξειδικευμένο υλικό για την προετοιμασία των μαθητών</li>
                        <li>✓ Στατιστικά και αναλυτικά στοιχεία επιδόσεων</li>
                        <li>✓ Ειδικές τιμές για ομαδικές συνδρομές</li>
                        <li>✓ Τεχνική υποστήριξη 24/7</li>
                    </ul>
                    <p><strong>Επικοινωνήστε μαζί μας για περισσότερες πληροφορίες</strong></p>
                    <p>Τηλέφωνο: +30 2310 123456</p>
                    <p>Email: info@drivetest.gr</p>
                </div>
            </div>
            
            <div class="form-column">
                <?php require_once '../templates/form_register_school.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/register.js"></script>
</body>
</html>