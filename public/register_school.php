<?php
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
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $responsible_person = trim($_POST['responsible_person']);
    $tax_id = trim($_POST['tax_id']);
    $license_number = trim($_POST['license_number']);
    $address = trim($_POST['address']);
    $street_number = trim($_POST['street_number']);
    $postal_code = trim($_POST['postal_code']);
    $city = trim($_POST['city']);
    $phone = trim($_POST['phone']);
    
    // Επιλεγμένες κατηγορίες (θα επιστρέφει πίνακα αν υπάρχουν πολλές επιλογές)
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $categories_json = json_encode($categories);

    // Έλεγχος για κενά υποχρεωτικά πεδία
    if (empty($school_name) || empty($email) || empty($password) || empty($responsible_person) || empty($tax_id)) {
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
                
                // Γεωκωδικοποίηση διεύθυνσης με Google Maps API εάν είναι διαθέσιμη
                $latitude = null;
                $longitude = null;
                if (!empty($address) && !empty($city)) {
                    $geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address . " " . $street_number . ", " . $city . ", " . $postal_code . ", Ελλάδα") . "&key=" . $config['google_maps_api_key'];
                    $geocode_response = @file_get_contents($geocode_url);
                    if ($geocode_response !== false) {
                        $geocode_data = json_decode($geocode_response, true);
                        if ($geocode_data['status'] === 'OK' && !empty($geocode_data['results'])) {
                            $location = $geocode_data['results'][0]['geometry']['location'];
                            $latitude = $location['lat'];
                            $longitude = $location['lng'];
                        }
                    }
                }
                
                // Χρήση transaction για την εγγραφή σχολής
                $mysqli->begin_transaction();
                
                try {
                    // Εισαγωγή χρήστη
                    $user_stmt = $mysqli->prepare("INSERT INTO users (fullname, email, password, role, verification_token, phone, address, street_number, postal_code, city, latitude, longitude) VALUES (?, ?, ?, 'school', ?, ?, ?, ?, ?, ?, ?, ?)");
                    $user_stmt->bind_param("sssssssssdd", $school_name, $email, $hashed_password, $verification_token, $phone, $address, $street_number, $postal_code, $city, $latitude, $longitude);
                    
                    if (!$user_stmt->execute()) {
                        throw new Exception("Σφάλμα κατά την εγγραφή χρήστη: " . $user_stmt->error);
                    }
                    
                    // Λήψη του ID του νέου χρήστη
                    $user_id = $mysqli->insert_id;
                    
                    // Εισαγωγή σχολής
                    $school_stmt = $mysqli->prepare("INSERT INTO schools (name, email, tax_id, responsible_person, license_number, address, street_number, postal_code, city, categories) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $school_stmt->bind_param("ssssssssss", $school_name, $email, $tax_id, $responsible_person, $license_number, $address, $street_number, $postal_code, $city, $categories_json);
                    
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

// Λήψη των διαθέσιμων κατηγοριών
$cat_query = "SELECT id, name FROM subscription_categories";
$cat_result = $mysqli->query($cat_query);
$categories = [];
if ($cat_result) {
    while ($cat = $cat_result->fetch_assoc()) {
        $categories[] = $cat;
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
            <h1><?= isset($translations['school_register_title']) ? $translations['school_register_title'] : 'Εγγραφή Σχολής' ?></h1>
            <p><?= isset($translations['school_register_subtitle']) ? $translations['school_register_subtitle'] : 'Συμπληρώστε τα παρακάτω στοιχεία για να εγγραφείτε ως σχολή στο DriveTest' ?></p>
            
            <div class="role_user">
                <img src="<?= BASE_URL ?>/assets/images/company_icon.png" alt="Σχολή">
                <span><?= isset($translations['school_role']) ? $translations['school_role'] : 'Εγγραφή Σχολής' ?></span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form action="register_school.php?lang=<?= $language ?>" method="post" class="needs-validation" novalidate>
                <h3>Βασικά Στοιχεία</h3>
                <input type="text" name="school_name" placeholder="<?= isset($translations['school_name_placeholder']) ? $translations['school_name_placeholder'] : 'Επωνυμία Σχολής*' ?>" required value="<?= isset($_POST['school_name']) ? htmlspecialchars($_POST['school_name']) : '' ?>">
                <input type="email" name="email" placeholder="<?= isset($translations['email_placeholder']) ? $translations['email_placeholder'] : 'Email*' ?>" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                <input type="text" name="responsible_person" placeholder="<?= isset($translations['responsible_person_placeholder']) ? $translations['responsible_person_placeholder'] : 'Υπεύθυνο Άτομο*' ?>" required value="<?= isset($_POST['responsible_person']) ? htmlspecialchars($_POST['responsible_person']) : '' ?>">
                <input type="text" name="tax_id" placeholder="<?= isset($translations['tax_id_placeholder']) ? $translations['tax_id_placeholder'] : 'ΑΦΜ*' ?>" required value="<?= isset($_POST['tax_id']) ? htmlspecialchars($_POST['tax_id']) : '' ?>">
                <input type="text" name="license_number" placeholder="<?= isset($translations['license_placeholder']) ? $translations['license_placeholder'] : 'Αριθμός Άδειας*' ?>" required value="<?= isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : '' ?>">
                <input type="tel" name="phone" placeholder="<?= isset($translations['phone_placeholder']) ? $translations['phone_placeholder'] : 'Τηλέφωνο' ?>" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                
                <h3>Διεύθυνση</h3>
                <input type="text" name="address" placeholder="<?= isset($translations['address_placeholder']) ? $translations['address_placeholder'] : 'Διεύθυνση' ?>" value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
                <input type="text" name="street_number" placeholder="<?= isset($translations['street_number_placeholder']) ? $translations['street_number_placeholder'] : 'Αριθμός' ?>" value="<?= isset($_POST['street_number']) ? htmlspecialchars($_POST['street_number']) : '' ?>">
                <input type="text" name="postal_code" placeholder="<?= isset($translations['postal_code_placeholder']) ? $translations['postal_code_placeholder'] : 'Ταχυδρομικός Κώδικας' ?>" value="<?= isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : '' ?>">
                <input type="text" name="city" placeholder="<?= isset($translations['city_placeholder']) ? $translations['city_placeholder'] : 'Πόλη' ?>" value="<?= isset($_POST['city']) ? htmlspecialchars($_POST['city']) : '' ?>">
                
                <h3>Κατηγορίες Εκπαίδευσης</h3>
                <div class="categories-container">
                    <?php foreach ($categories as $category): ?>
                    <div class="category-checkbox">
                        <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($category['name']) ?>" id="cat_<?= $category['id'] ?>" <?= (isset($_POST['categories']) && in_array($category['name'], $_POST['categories'])) ? 'checked' : '' ?>>
                        <label for="cat_<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <h3>Κωδικός Πρόσβασης</h3>
                <div class="password-visibility">
                    <input type="password" name="password" id="password" placeholder="<?= isset($translations['password_placeholder']) ? $translations['password_placeholder'] : 'Συνθηματικό*' ?>" required>
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password" style="width: 20px;">
                    </span>
                </div>
                
                <div class="password-visibility">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="<?= isset($translations['confirm_password_placeholder']) ? $translations['confirm_password_placeholder'] : 'Επιβεβαίωση Συνθηματικού*' ?>" required>
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password" style="width: 20px;">
                    </span>
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
                <hr class="divider">
            </form>
            <p class="login-link"><?= isset($translations['login_link']) ? $translations['login_link'] : 'Έχετε ήδη λογαριασμό; <a href="' . BASE_URL . '/public/login.php?lang=' . $language . '">Συνδεθείτε</a>' ?></p>
        </div>
        
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