<?php
// Διασφάλιση ότι οι μεταβλητές είναι διαθέσιμες
$error = $error ?? '';
$success = $success ?? '';
$language = $language ?? ($_SESSION['language'] ?? 'el');
$translations = $translations ?? [];
$use_language_helper = function_exists('__');
?>

<div class="form-container">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success" role="alert"><?= $success ?></div>
    <?php endif; ?>
    
    <form action="<?= BASE_URL ?>/public/recover_password.php" method="post" class="needs-validation" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">
                <?= $use_language_helper ? __('email') : 
                    (isset($translations['email']) ? $translations['email'] : 'Email') ?>
            </label>
            <input type="email" name="email" id="email" class="form-control" 
                   placeholder="<?= $use_language_helper ? __('email_placeholder') : 
                                (isset($translations['email_placeholder']) ? $translations['email_placeholder'] : 'Email') ?>" 
                   required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>
        
        <button type="submit" class="btn btn-primary w-100">
            <?= $use_language_helper ? __('reset_button') : 
                (isset($translations['reset_button']) ? $translations['reset_button'] : 'Αποστολή Συνδέσμου') ?>
        </button>
    </form>
</div>