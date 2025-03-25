<?php
// Βεβαιωθείτε ότι το config φορτώνεται σωστά αν χρειάζεται
if (!isset($config) && file_exists(dirname(__DIR__, 2) . '/config/config.php')) {
    $config = require_once dirname(__DIR__, 2) . '/config/config.php';
}

// Ορισμός της έκδοσης της εφαρμογής
$app_version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
?>

<footer class="admin-footer">
    <div class="footer-container">

               
        <div class="footer-links">
            <a href="<?= $config['base_url'] ?? '' ?>/admin/dashboard.php" class="footer-link">Αρχική</a>
            <a href="<?= $config['base_url'] ?? '' ?>/admin/settings.php" class="footer-link">Ρυθμίσεις</a>
            <a href="<?= $config['base_url'] ?? '' ?>" class="footer-link" target="_blank">Δημόσια Σελίδα</a>
        </div>
        <div class="footer-copyright">
            &copy; <?= date('Y') ?> DriveTest Admin Panel - Με επιφύλαξη παντός δικαιώματος
        </div>
        
        <div class="footer-version">
            v<?= htmlspecialchars($app_version) ?>
        </div>
    </div>
</footer>

</body>
</html>