<div class="form-container">
    <div class="logo">
        <a href="<?= BASE_URL ?>/public/index.php?lang=<?= $language ?>">
            <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest Logo">
        </a>
    </div>
    
    <h1><?= isset($translations['school_register_title']) ? $translations['school_register_title'] : 'Εγγραφή Σχολής' ?></h1>
    <p><?= isset($translations['school_register_subtitle']) ? $translations['school_register_subtitle'] : 'Συμπληρώστε τα παρακάτω στοιχεία για να αποκτήσετε πρόσβαση στην πλατφόρμα DriveTest ως σχολή!' ?></p>
    
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
        <input type="text" name="school_name" id="school_name" class="form-control" placeholder="<?= isset($translations['school_name_placeholder']) ? $translations['school_name_placeholder'] : 'Επωνυμία Σχολής*' ?>" required value="<?= isset($_POST['school_name']) ? htmlspecialchars($_POST['school_name']) : '' ?>">
        
        <input type="email" name="email" id="email" class="form-control" placeholder="<?= isset($translations['email_placeholder']) ? $translations['email_placeholder'] : 'Email*' ?>" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        
        <input type="text" name="tax_id" id="tax_id" class="form-control" placeholder="<?= isset($translations['tax_id_placeholder']) ? $translations['tax_id_placeholder'] : 'ΑΦΜ*' ?>" required value="<?= isset($_POST['tax_id']) ? htmlspecialchars($_POST['tax_id']) : '' ?>">
        
        <div class="password-visibility">
            <input type="password" name="password" id="password" class="form-control" placeholder="<?= isset($translations['password_placeholder']) ? $translations['password_placeholder'] : 'Συνθηματικό*' ?>" required>
            <span class="password-toggle" onclick="togglePassword('password')">
                <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password" style="width: 20px;">
            </span>
        </div>
        
        <div class="password-visibility">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="<?= isset($translations['confirm_password_placeholder']) ? $translations['confirm_password_placeholder'] : 'Επιβεβαίωση Συνθηματικού*' ?>" required>
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