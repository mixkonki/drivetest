<?php
// Ξεκινάμε το output buffering για να αποφύγουμε το "headers already sent" error
ob_start();

require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';  // Προσθήκη του mailer
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

// Αρχικοποίηση μεταβλητών
$errors = [];
$success = false;
$success_message = '';
$form_data = [
    'fullname' => '',
    'email' => '',
    'role' => 'user',
    'phone' => '',
    'address' => '',
    'city' => '',
    'postal_code' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Καθαρισμός και επικύρωση δεδομένων
    $form_data = [
        'fullname' => trim($_POST['fullname'] ?? ''),
        'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
        'role' => trim($_POST['role'] ?? 'user'),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? '')
    ];
    
    // Κωδικός και επιβεβαίωση κωδικού
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Έλεγχος υποχρεωτικών πεδίων
    if (empty($form_data['fullname'])) {
        $errors[] = "Το ονοματεπώνυμο είναι υποχρεωτικό.";
    }
    
    if (empty($form_data['email'])) {
        $errors[] = "Το email είναι υποχρεωτικό.";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Το email δεν είναι έγκυρο.";
    }
    
    if (empty($password)) {
        $errors[] = "Ο κωδικός είναι υποχρεωτικός.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Οι κωδικοί δεν ταιριάζουν.";
    }
    
    // Έλεγχος αν υπάρχει το email
    if (empty($errors)) {
        $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        if (!$check_stmt) {
            $errors[] = "Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error;
        } else {
            $check_stmt->bind_param("s", $form_data['email']);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $errors[] = "Το email υπάρχει ήδη. Παρακαλώ χρησιμοποιήστε διαφορετικό email.";
            }
            $check_stmt->close();
        }
    }
    
    // Αν δεν υπάρχουν σφάλματα, καταχώρηση του χρήστη
    if (empty($errors)) {
        try {
            // Κρυπτογράφηση του κωδικού
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Προετοιμασία του ερωτήματος
            $stmt = $mysqli->prepare("
                INSERT INTO users (fullname, email, role, password, verified, phone, address, city, postal_code, created_at, status) 
                VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, NOW(), 'active')
            ");
            
            if (!$stmt) {
                throw new Exception("Προετοιμασία query απέτυχε: " . $mysqli->error);
            }
            
            $stmt->bind_param(
                "ssssssss", 
                $form_data['fullname'], 
                $form_data['email'], 
                $form_data['role'], 
                $hashed_password,
                $form_data['phone'],
                $form_data['address'],
                $form_data['city'],
                $form_data['postal_code']
            );
            
            if ($stmt->execute()) {
                $user_id = $mysqli->insert_id;
                $success = true;
                
                // Προσθήκη σχολής αν ο ρόλος είναι 'school'
                if ($form_data['role'] === 'school') {
                    $school_stmt = $mysqli->prepare("
                        INSERT INTO schools (id, name, email, address, city, postal_code)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($school_stmt) {
                        $school_stmt->bind_param(
                            "isssss", 
                            $user_id, 
                            $form_data['fullname'], 
                            $form_data['email'],
                            $form_data['address'],
                            $form_data['city'],
                            $form_data['postal_code']
                        );
                        $school_stmt->execute();
                        $school_stmt->close();
                    }
                }
                
                // Αποστολή email στο νέο χρήστη
                $email_sent = sendWelcomeEmail($form_data['email'], $form_data['fullname'], $password, $form_data['role']);
                
                // Καθαρισμός της φόρμας αν είναι επιτυχής
                $form_data = [
                    'fullname' => '',
                    'email' => '',
                    'role' => 'user',
                    'phone' => '',
                    'address' => '',
                    'city' => '',
                    'postal_code' => ''
                ];
                
                // Ορισμός του μηνύματος επιτυχίας
                $success_message = "Ο χρήστης προστέθηκε επιτυχώς";
                if (!$email_sent) {
                    $success_message .= ", αλλά δεν ήταν δυνατή η αποστολή email.";
                }
                
                // Τώρα μπορούμε να χρησιμοποιήσουμε ασφαλώς την header() χάρη στο output buffering
                header("Location: users.php?success=" . urlencode($success_message));
                exit();
            } else {
                throw new Exception("Προσθήκη χρήστη απέτυχε: " . $stmt->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Σφάλμα: " . $e->getMessage();
            
            // Καταγραφή σφάλματος
            if (function_exists('log_debug')) {
                log_debug("Σφάλμα στο add_user.php: " . $e->getMessage());
            } else {
                error_log("Σφάλμα στο add_user.php: " . $e->getMessage());
            }
        }
    }
}

/**
 * Αποστολή email καλωσορίσματος στο νέο χρήστη
 * 
 * @param string $email Email του χρήστη
 * @param string $fullname Όνομα του χρήστη
 * @param string $password Ο κωδικός του χρήστη (μη κρυπτογραφημένος)
 * @param string $role Ο ρόλος του χρήστη
 * @return bool Αν η αποστολή ήταν επιτυχής
 */
function sendWelcomeEmail($email, $fullname, $password, $role) {
    $subject = "Καλώς ήρθατε στο DriveTest!";
    
    // Διαφορετικό μήνυμα ανάλογα με το ρόλο
    $role_text = '';
    switch ($role) {
        case 'admin':
            $role_text = "διαχειριστή";
            break;
        case 'school':
            $role_text = "σχολής";
            break;
        case 'student':
            $role_text = "μαθητή";
            break;
        default:
            $role_text = "χρήστη";
    }
    
    $login_url = BASE_URL . '/public/login.php';
    
    $message = "
    <html>
    <head>
        <title>Καλώς ήρθατε στο DriveTest</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #aa3636; color: white; padding: 10px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
            .button { display: inline-block; padding: 10px 20px; background-color: #aa3636; color: white; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Καλώς ήρθατε στο DriveTest!</h1>
            </div>
            <div class='content'>
                <p>Αγαπητέ/ή <strong>$fullname</strong>,</p>
                
                <p>Σας καλωσορίζουμε στην πλατφόρμα DriveTest! Ο λογαριασμός σας έχει δημιουργηθεί επιτυχώς 
                με ρόλο <strong>$role_text</strong>.</p>
                
                <p>Τα στοιχεία σύνδεσής σας είναι:</p>
                <ul>
                    <li><strong>Email:</strong> $email</li>
                    <li><strong>Κωδικός:</strong> $password</li>
                </ul>
                
                <p>Για λόγους ασφαλείας, παρακαλούμε να αλλάξετε τον κωδικό σας μετά την πρώτη σύνδεση.</p>
                
                <p><a href='$login_url' class='button'>Σύνδεση στην πλατφόρμα</a></p>
                
                <p>Αν αντιμετωπίσετε οποιοδήποτε πρόβλημα, μη διστάσετε να επικοινωνήσετε μαζί μας.</p>
                
                <p>Με εκτίμηση,<br>Η ομάδα του DriveTest</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date("Y") . " DriveTest - Όλα τα δικαιώματα προστατεύονται.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Χρήση της κλάσης Emailer από το mailer.php
    return send_mail($email, $subject, $message);
}
?>

<main class="admin-container">
    <h1 class="admin-title">Προσθήκη Νέου Χρήστη</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Σφάλμα!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Επιτυχία!</strong> <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <form action="" method="post" class="admin-form" role="form" aria-label="Φόρμα Προσθήκης Χρήστη">
        <div class="form-row">
            <div class="form-column">
                <h3>Βασικά Στοιχεία</h3>
                
                <div class="form-group">
                    <label for="fullname">Ονοματεπώνυμο:</label>
                    <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($form_data['fullname']) ?>" required aria-required="true" class="form-input">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" required aria-required="true" class="form-input">
                </div>

                <div class="form-group">
                    <label for="role">Ρόλος:</label>
                    <select id="role" name="role" aria-required="true" class="form-select" onchange="toggleSchoolFields()">
                        <option value="user" <?= $form_data['role'] === 'user' ? 'selected' : '' ?>>Χρήστης</option>
                        <option value="student" <?= $form_data['role'] === 'student' ? 'selected' : '' ?>>Μαθητής</option>
                        <option value="school" <?= $form_data['role'] === 'school' ? 'selected' : '' ?>>Σχολή</option>
                        <option value="admin" <?= $form_data['role'] === 'admin' ? 'selected' : '' ?>>Διαχειριστής</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="phone">Τηλέφωνο:</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($form_data['phone']) ?>" class="form-input">
                </div>
            </div>
            
            <div class="form-column" id="address-column">
                <h3>Στοιχεία Διεύθυνσης</h3>
                
                <div class="form-group">
                    <label for="address">Διεύθυνση:</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($form_data['address']) ?>" class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="city">Πόλη:</label>
                    <input type="text" id="city" name="city" value="<?= htmlspecialchars($form_data['city']) ?>" class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="postal_code">Ταχυδρομικός Κώδικας:</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($form_data['postal_code']) ?>" class="form-input">
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-column">
                <h3>Στοιχεία Λογαριασμού</h3>
                
                <div class="form-group">
                    <label for="password">Κωδικός:</label>
                    <input type="password" id="password" name="password" required aria-required="true" class="form-input" minlength="8">
                    <small>Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Επαλήθευση Κωδικού:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required aria-required="true" class="form-input" minlength="8">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary" aria-label="Προσθήκη χρήστη">Προσθήκη Χρήστη</button>
            <a href="users.php" class="btn-secondary">Επιστροφή</a>
        </div>
    </form>
</main>

<script>
function toggleSchoolFields() {
    const role = document.getElementById('role').value;
    const addressColumn = document.getElementById('address-column');
    
    if (role === 'school') {
        addressColumn.style.display = 'block';
        document.querySelectorAll('#address-column input').forEach(input => {
            input.required = true;
        });
    } else {
        addressColumn.style.display = role === 'user' ? 'none' : 'block';
        document.querySelectorAll('#address-column input').forEach(input => {
            input.required = false;
        });
    }
}

// Εκτέλεση κατά τη φόρτωση της σελίδας
document.addEventListener('DOMContentLoaded', function() {
    toggleSchoolFields();
});
</script>

<?php 
require_once 'includes/admin_footer.php';
// Κλείσιμο του output buffer και αποστολή του περιεχομένου
ob_end_flush();
?>