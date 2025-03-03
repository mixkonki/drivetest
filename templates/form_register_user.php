<div class="form-container">
    <div class="logo">
        <a href="<?= BASE_URL ?>/public/index.php?lang=<?= $language ?>">
            <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest Logo">
        </a>
    </div>
    <h1><?= isset($translations['register_title']) ? $translations['register_title'] : 'Εγγραφείτε' ?></h1>
    <p><?= isset($translations['register_subtitle']) ? $translations['register_subtitle'] : 'Αποκτήστε πρόσβαση στο <br><strong>DriveTest</strong> μέσα σε 30 δευτερόλεπτα!' ?></p>
    <div class="role_user">
        <img src="<?= BASE_URL ?>/assets/images/driver_icon.png" alt="Χρήστης">
        <span><?= isset($translations['user_role']) ? $translations['user_role'] : 'Εγγραφή Χρήστη' ?></span>
    </div>
    <form action="register_user.php?lang=<?= $language ?>" method="post" class="needs-validation" novalidate>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert"><?= $success ?></div>
        <?php endif; ?>

        <input type="text" name="fullname" id="fullname" class="form-control" placeholder="<?= isset($translations['fullname_placeholder']) ? $translations['fullname_placeholder'] : 'Ονοματεπώνυμο' ?>" required>
        <input type="email" name="email" id="email" class="form-control" placeholder="<?= isset($translations['email_placeholder']) ? $translations['email_placeholder'] : 'Email' ?>" required>

        <div class="password-visibility">
            <input type="password" name="password" id="password" class="form-control" placeholder="<?= isset($translations['password_placeholder']) ? $translations['password_placeholder'] : 'Συνθηματικό' ?>" required>
            <span class="password-toggle" onclick="togglePassword('password')">
                <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password" style="width: 20px;">
            </span>
        </div>

        <div class="password-visibility">
    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="<?= isset($translations['password_placeholder']) ? $translations['password_placeholder'] : 'Επιβεβαίωση συνθηματικού' ?>" required>

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
                <input type="checkbox" name="human_check" required> <?= isset($translations['not_robot']) ? $translations['not_robot'] : 'Δεν είμαι ρομπότ' ?>
            </label>
            <label>
                <input type="checkbox" name="terms_check" required> <?= isset($translations['accept_terms']) ? $translations['accept_terms'] : 'Αποδέχομαι τους <a href="#">όρους χρήσης</a>' ?>
            </label>
        </div>

        <button type="submit" class="btn-primary"> <?= isset($translations['register_button']) ? $translations['register_button'] : 'Εγγραφή' ?></button>
        <hr class="divider">

    </form>
    <br><p class="login-link"><?= isset($translations['login_link']) ? $translations['login_link'] : 'Έχετε ήδη λογαριασμό? <a href="' . BASE_URL . '/public/login.php?lang=' . $language . '">Συνδεθείτε</a>' ?></p>

</div>


