<?php
// Διαδρομή: /includes/scripts.php
/**
 * Αυτό το αρχείο περιέχει όλα τα scripts JavaScript που χρησιμοποιούνται στην εφαρμογή
 */
?>

<!-- Προσθήκη αρχικής κλάσης loading στο body για αποφυγή FOUC -->
<script>
document.body.classList.add('loading');
window.addEventListener('load', function() {
    document.body.classList.remove('loading');
});
// Fallback για την περίπτωση που δεν πυροδοτηθεί το 'load' event
setTimeout(function() {
    document.body.classList.remove('loading');
}, 1000);
</script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

<!-- Βασικά scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<!-- Google Maps API (αν χρειάζεται) -->
<?php if (isset($load_map_js) && $load_map_js === true): ?>
    <!-- Φόρτωση του Google Maps API μόνο αν δεν έχει ήδη φορτωθεί -->
    <script>
        // Έλεγχος αν υπάρχει ήδη το Google Maps API
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
            // Βασικές μεταβλητές για το Maps
            window.mapsInitialized = false;
            window.initMapCallbacks = [];
            
            // Καθολική συνάρτηση αρχικοποίησης που θα κληθεί από το API
            window.initMapComponents = function() {
                window.mapsInitialized = true;
                
                // Εκτέλεση όλων των callbacks
                window.initMapCallbacks.forEach(function(callback) {
                    if (typeof callback === 'function') {
                        callback();
                    }
                });
                
                // Καθαρισμός των callbacks
                window.initMapCallbacks = [];
            };
            
            // Δημιουργία του script tag για φόρτωση του API
            document.write('<script src="https://maps.googleapis.com/maps/api/js?key=<?= $config['google_maps_api_key'] ?>&libraries=places&callback=initMapComponents" async defer><\/script>');
        }
        
        // Βοηθητική συνάρτηση για προσθήκη callback
        window.addMapInitCallback = function(callback) {
            if (window.mapsInitialized) {
                // Αν έχει ήδη αρχικοποιηθεί, εκτέλεση άμεσα
                callback();
            } else {
                // Διαφορετικά προσθήκη στα callbacks
                window.initMapCallbacks.push(callback);
            }
        };
    </script>
    <script src="<?= BASE_URL ?>/assets/js/maps.js"></script>
<?php endif; ?>

<!-- Ειδικά scripts για χρήστες -->
<?php if (function_exists('is_logged_in') && is_logged_in()): ?>
    <?php if (function_exists('has_role') && has_role('admin')): ?>
        <script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
    <?php elseif (function_exists('has_role') && has_role('school')): ?>
        <script src="<?= BASE_URL ?>/assets/js/school.js"></script>
    <?php elseif (function_exists('has_role') && has_role('student')): ?>
        <script src="<?= BASE_URL ?>/assets/js/student.js"></script>
    <?php else: ?>
        <script src="<?= BASE_URL ?>/assets/js/user.js"></script>
    <?php endif; ?>
<?php endif; ?>

<!-- Conditional JS loading με βάση τις flag μεταβλητές -->
<?php
$js_flags = [
    'load_chart_js' => '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>',
    'load_auth_js' => '<script src="' . BASE_URL . '/assets/js/auth.js"></script>',
    'load_school_dashboard_js' => '<script src="' . BASE_URL . '/assets/js/school-dashboard.js"></script>',
    'load_user_profile_js' => '<script src="' . BASE_URL . '/assets/js/user_profile.js"></script>'
];

foreach ($js_flags as $flag => $js_include) {
    if (isset($$flag) && $$flag === true) {
        echo $js_include . "\n";
    }
}
?>

<!-- Αυτόματη φόρτωση script με βάση το όνομα της σελίδας -->
<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php'); // Αφαιρεί την κατάληξη .php
$page_specific_js = BASE_URL . '/assets/js/' . $current_page . '.js';
$page_specific_js_file = BASE_PATH . '/assets/js/' . $current_page . '.js';
if (file_exists($page_specific_js_file)) {
    echo '<script src="' . $page_specific_js . '"></script>';
}
?>

<!-- Additional JS (αν έχει οριστεί από το σενάριο) -->
<?php if (isset($additional_js)): ?>
    <?= $additional_js ?>
<?php endif; ?>

<!-- Script για κλείσιμο των alerts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Κλείσιμο των alerts
    const closeButtons = document.querySelectorAll('.close-alert');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            }
        });
    });
    
    // Αυτόματο κλείσιμο των alerts μετά από 5 δευτερόλεπτα
    const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});
</script>