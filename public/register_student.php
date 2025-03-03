<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';

// Έλεγχος αν υπάρχει token
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("Location: " . BASE_URL . "/public/login.php?error=invalid_invitation");
    exit();
}

$token = $_GET['token'];
$error = "";
$success = "";
$invitation = null;
$school_name = "";

// Λήψη γλώσσης
$language = isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'al', 'ru', 'en', 'de']) ? $_GET['lang'] : 'el';
$translationsPath = dirname(__DIR__) . '/languages/';
$translations = require $translationsPath . "{$language}.php";

// Επαλήθευση του token και λήψη στοιχείων πρόσκλησης
$query = "SELECT si.*, s.name as school_name FROM student_invitations si 
          JOIN schools s ON si.school_id = s.id 
          WHERE si.token = ? AND si.used = 0 AND si.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)";
          
$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Η πρόσκληση δεν είναι έγκυρη ή έχει λήξει.";
} else {
    $invitation = $result->fetch_assoc();
    $school_name = $invitation['school_name'];
}
$stmt->close();

// Επεξεργασία της φόρμας εγγραφής
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation) {
    $fullname = trim($_POST['fullname']);
    $email = $invitation['email']; // Χρησιμοποιούμε το email από την πρόσκληση
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Έλεγχος για κενά πεδία
    if (empty($fullname) || empty($password) || empty($confirm_password)) {
        $error = "Παρακαλώ συμπληρώστε όλα τα πεδία.";
    } 
    // Έλεγχος αν οι κωδικοί ταιριάζουν
    elseif ($password !== $confirm_password) {
        $error = "Οι κωδικοί δεν ταιριάζουν.";
    } 
    // Έλεγχος για την πολυπλοκότητα του κωδικού
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/', $password)) {
        $error = "Ο κωδικός πρέπει να περιέχει τουλάχιστον 8 χαρακτήρες, ένα κεφαλαίο γράμμα, έναν αριθμό και έναν ειδικό χαρακτήρα!";
    } 
    else {
        // Έλεγχος αν το email υπάρχει ήδη
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $error = "Υπάρχει ήδη λογαριασμός με αυτό το email. Παρακαλώ συνδεθείτε.";
        } else {
            // Προσθήκη του νέου μαθητή
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $verification_token = bin2hex(random_bytes(32));
            
            // Χρήση transaction για εγγραφή
            $mysqli->begin_transaction();
            
            try {
                // Εισαγωγή χρήστη
                $insert_query = "INSERT INTO users (fullname, email, password, role, school_id, verification_token, email_verified) 
                                VALUES (?, ?, ?, 'student', ?, ?, 1)";
                                
                $stmt_insert = $mysqli->prepare($insert_query);
                $stmt_insert->bind_param("sssss", $fullname, $email, $hashed_password, $invitation['school_id'], $verification_token);
                
                if ($stmt_insert->execute()) {
                    // Ενημέρωση του status της πρόσκλησης
                    $update_query = "UPDATE student_invitations SET used = 1, used_at = NOW() WHERE id = ?";
                    $stmt_update = $mysqli->prepare($update_query);
                    $stmt_update->bind_param("i", $invitation['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    $mysqli->commit();
                    
                    // Αποστολή email επιβεβαίωσης
                    $subject = "Καλώς ήρθατε στο DriveTest - Επιτυχής εγγραφή";
                    $message = "<h2>Καλώς ήρθατε στο DriveTest!</h2>
                                <p>Αγαπητέ/ή " . htmlspecialchars($fullname) . ",</p>
                                <p>Η εγγραφή σας ολοκληρώθηκε με επιτυχία και ο λογαριασμός σας έχει συνδεθεί με την σχολή <strong>" . htmlspecialchars($school_name) . "</strong>.</p>
                                <p>Μπορείτε να συνδεθείτε στο λογαριασμό σας από τον παρακάτω σύνδεσμο:</p>
                                <p><a href='" . BASE_URL . "/public/login.php' style='padding: 10px 15px; background-color: #aa3636; color: white; text-decoration: none; border-radius: 5px;'>Σύνδεση στο DriveTest</a></p>";
                    
                    send_mail($email, $subject, $message);
                    
                    $success = "Η εγγραφή σας ολοκληρώθηκε με επιτυχία! Μπορείτε να συνδεθείτε στο λογαριασμό σας.";
                    
                    // Ανακατεύθυνση στην σελίδα σύνδεσης μετά από 5 δευτερόλεπτα
                    header("Refresh: 5; URL=" . BASE_URL . "/public/login.php?success=registered");
                } else {
                    throw new Exception("Σφάλμα κατά την εγγραφή: " . $stmt_insert->error);
                }
                $stmt_insert->close();
            } catch (Exception $e) {
                $mysqli->rollback();
                $error = "Σφάλμα κατά την εγγραφή: " . $e->getMessage();
            }
        }
        $stmt_check->close();
    }
}

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<main class="container">
    <div class="columns-wrapper">
        <div class="form-column">
            <div class="logo">
                <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest Logo">
            </div>
            
            <?php if ($error && !$invitation): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
                <p>Παρακαλώ επικοινωνήστε με τη σχολή σας για μια νέα πρόσκληση.</p>
                <p><a href="<?= BASE_URL ?>/public/login.php" class="btn-primary">Επιστροφή στην αρχική</a></p>
            <?php elseif ($success): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
                <p>Ανακατεύθυνση στη σελίδα σύνδεσης...</p>
            <?php else: ?>
                <h1><?= isset($translations['student_register_title']) ? $translations['student_register_title'] : 'Εγγραφή Μαθητή' ?></h1>
                <p><?= isset($translations['student_register_subtitle']) ? $translations['student_register_subtitle'] : 'Ολοκληρώστε την εγγραφή σας για να αποκτήσετε πρόσβαση στο εκπαιδευτικό υλικό' ?></p>
                
                <div class="invitation-details">
                    <h3>Πληροφορίες Πρόσκλησης</h3>
                    <p><strong>Σχολή:</strong> <?= htmlspecialchars($school_name) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($invitation['email']) ?></p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form action="register_student.php?token=<?= htmlspecialchars($token) ?>&lang=<?= $language ?>" method="post" class="needs-validation" novalidate>
                    <div class="form-group">
                        <label for="fullname">Ονοματεπώνυμο</label>
                        <input type="text" name="fullname" id="fullname" class="form-control" placeholder="<?= isset($translations['fullname_placeholder']) ? $translations['fullname_placeholder'] : 'Ονοματεπώνυμο' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($invitation['email']) ?>" readonly>
                        <small class="form-text text-muted">Το email δεν μπορεί να αλλαχθεί.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Συνθηματικό</label>
                        <div class="password-visibility">
                            <input type="password" name="password" id="password" class="form-control" placeholder="<?= isset($translations['password_placeholder']) ? $translations['password_placeholder'] : 'Συνθηματικό' ?>" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password" style="width: 20px;">
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Επιβεβαίωση Συνθηματικού</label>
                        <div class="password-visibility">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="<?= isset($translations['confirm_password_placeholder']) ? $translations['confirm_password_placeholder'] : 'Επιβεβαίωση Συνθηματικού' ?>" required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password" style="width: 20px;">
                            </span>
                        </div>
                    </div>
                    
                    <p class="text_pass"><?= isset($translations['password_requirements']) ? $translations['password_requirements'] : 'Το συνθηματικό πρέπει να περιέχει:' ?></p>
                    <ul class="password-hint">
                        <li id="hint-length">❌ <?= isset($translations['password_length']) ? $translations['password_length'] : '8-16 χαρακτήρες' ?></li>
                        <li id="hint-uppercase">❌ <?= isset($translations['password_uppercase']) ? $translations['password_uppercase'] : '1 κεφαλαίο γράμμα' ?></li>
                        <li id="hint-number">❌ <?= isset($translations['password_number']) ? $translations['password_number'] : '1 αριθμός' ?></li>
                        <li id="hint-special">❌ <?= isset($translations['password_special']) ? $translations['password_special'] : '1 ειδικός χαρακτήρας' ?></li>
                    </ul>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="terms_check" required> <?= isset($translations['accept_terms']) ? $translations['accept_terms'] : 'Αποδέχομαι τους <a href="#">όρους χρήσης</a>' ?>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-primary"><?= isset($translations['register_button']) ? $translations['register_button'] : 'Εγγραφή' ?></button>
                </form>
                
                <p class="login-link"><?= isset($translations['login_link']) ? $translations['login_link'] : 'Έχετε ήδη λογαριασμό; <a href="' . BASE_URL . '/public/login.php?lang=' . $language . '">Συνδεθείτε</a>' ?></p>
            <?php endif; ?>
        </div>
        
        <div class="info-box">
            <div>
                <h2>Πλεονεκτήματα Εγγραφής</h2>
                <ul>
                    <li>✓ Πρόσβαση σε εκπαιδευτικό υλικό και τεστ</li>
                    <li>✓ Παρακολούθηση της προόδου σας</li>
                    <li>✓ Προσομοίωση εξετάσεων</li>
                    <li>✓ Στοχευμένη εξάσκηση σε δύσκολες ερωτήσεις</li>
                    <li>✓ Πρόσβαση από οποιαδήποτε συσκευή</li>
                </ul>
                <p><strong>Για οποιαδήποτε απορία, επικοινωνήστε με την σχολή σας.</strong></p>
            </div>
        </div>
    </div>
</main>

<script>
// JavaScript για την επικύρωση του κωδικού
document.getElementById('password').addEventListener('input', validatePassword);
document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

function validatePassword() {
    const password = document.getElementById('password').value;
    
    // Έλεγχος μήκους
    if (password.length >= 8 && password.length <= 16) {
        document.getElementById('hint-length').innerHTML = '✅ 8-16 χαρακτήρες';
    } else {
        document.getElementById('hint-length').innerHTML = '❌ 8-16 χαρακτήρες';
    }
    
    // Έλεγχος για κεφαλαίο γράμμα
    if (/[A-Z]/.test(password)) {
        document.getElementById('hint-uppercase').innerHTML = '✅ 1 κεφαλαίο γράμμα';
    } else {
        document.getElementById('hint-uppercase').innerHTML = '❌ 1 κεφαλαίο γράμμα';
    }
    
    // Έλεγχος για αριθμό
    if (/\d/.test(password)) {
        document.getElementById('hint-number').innerHTML = '✅ 1 αριθμός';
    } else {
        document.getElementById('hint-number').innerHTML = '❌ 1 αριθμός';
    }
    
    // Έλεγχος για ειδικό χαρακτήρα
    if (/[\W_]/.test(password)) {
        document.getElementById('hint-special').innerHTML = '✅ 1 ειδικός χαρακτήρας';
    } else {
        document.getElementById('hint-special').innerHTML = '❌ 1 ειδικός χαρακτήρας';
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirm_password = document.getElementById('confirm_password').value;
    
    if (password !== confirm_password) {
        document.getElementById('confirm_password').setCustomValidity('Οι κωδικοί δεν ταιριάζουν!');
    } else {
        document.getElementById('confirm_password').setCustomValidity('');
    }
}

function togglePassword(inputId) {
    const passwordInput = document.getElementById(inputId);
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
    } else {
        passwordInput.type = 'password';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>