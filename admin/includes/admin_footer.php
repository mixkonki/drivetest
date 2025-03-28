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

<!-- Admin specific scripts - αυτόματη φόρτωση JavaScript -->
<?php require_once dirname(__FILE__) . '/admin_scripts.php'; ?>
</body>
</html><?php
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

<!-- jQuery (αν χρειάζεται) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Φόρτωση των βασικών JS αρχείων -->
<?php if (isset($common_js_files)): ?>
    <?php foreach ($common_js_files as $js_file): ?>
        <?php if (file_exists(BASE_PATH . $js_file)): ?>
            <script src="<?= BASE_URL . $js_file ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Φόρτωση των ειδικών admin JS, αν υπάρχουν -->
<?php if (isset($admin_js_files) && !empty($admin_js_files)): ?>
    <?php $loaded_js_files = []; // Για αποφυγή διπλών φορτώσεων ?>
    
    <?php foreach ($admin_js_files as $js_file): ?>
        <?php if (!in_array($js_file, $loaded_js_files) && file_exists(BASE_PATH . $js_file)): ?>
            <script src="<?= BASE_URL . $js_file ?>"></script>
            <?php $loaded_js_files[] = $js_file; ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Φόρτωση εξωτερικών scripts -->
<?php if (isset($external_scripts_to_load) && !empty($external_scripts_to_load)): ?>
    <?php foreach ($external_scripts_to_load as $js_url): ?>
        <script src="<?= $js_url ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Admin specific scripts - αυτόματη φόρτωση JavaScript -->
<?php require_once dirname(__FILE__) . '/admin_scripts.php'; ?>

<!-- Επιπλέον scripts που ορίζονται από τη σελίδα -->
<?php if (isset($additional_js) && !empty($additional_js)): ?>
    <?= $additional_js ?>
<?php endif; ?>
</body>
</html>