<?php
/**
 * Φόρτωση όλων των απαραίτητων JavaScript αρχείων
 */

// Βασικά JS που πρέπει να φορτώνονται πάντα
$main_scripts = [
    'main.js', // Βασικό JavaScript
];

// JS αρχεία που φορτώνονται βάσει της σελίδας
$page_specific_scripts = [
    'test.js' => $load_test_js ?? false,             // Για τις σελίδες τεστ
    'auth.js' => $load_auth_js ?? false,             // Για σελίδες αυθεντικοποίησης
    'dashboard.js' => $load_dashboard_js ?? false,   // Για τους πίνακες ελέγχου
    'profile.js' => $load_profile_js ?? false,       // Για τις σελίδες προφίλ
    'map.js' => $load_map_js ?? false,           // Για σελίδες με χάρτες
    'chart.js' => $load_chart_js ?? false,         // Για σελίδες με γραφήματα
];
?>

<!-- jQuery Library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Popper.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

<!-- Βασικά JS -->
<?php foreach ($main_scripts as $script): ?>
<script src="<?= BASE_URL ?>/assets/js/<?= $script ?>"></script>
<?php endforeach; ?>

<!-- Ειδικά JS βάσει σελίδας -->
<?php foreach ($page_specific_scripts as $script => $load): ?>
    <?php if ($load): ?>
<script src="<?= BASE_URL ?>/assets/js/<?= $script ?>"></script>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Επιπλέον JS (προαιρετικό) -->
<?= $additional_js ?? '' ?>

<!-- Service Worker για PWA -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= BASE_URL ?>/public/service-worker.js')
            .then(function(registration) {
                console.log('Service Worker registered with scope: ', registration.scope);
            })
            .catch(function(error) {
                console.log('Service Worker registration failed: ', error);
            });
    });
}
</script>