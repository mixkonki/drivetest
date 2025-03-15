<?php
// Διαδρομή: /includes/scripts.php

/**
 * Αυτό το αρχείο περιέχει όλα τα scripts JavaScript που χρησιμοποιούνται στην εφαρμογή
 */
?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

<!-- Βασικά scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<!-- Ειδικά scripts για χρήστες -->
<?php if (is_logged_in()): ?>
    <?php if (has_role('admin')): ?>
        <script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
    <?php elseif (has_role('school')): ?>
        <script src="<?= BASE_URL ?>/assets/js/school.js"></script>
    <?php elseif (has_role('student')): ?>
        <script src="<?= BASE_URL ?>/assets/js/student.js"></script>
    <?php else: ?>
        <script src="<?= BASE_URL ?>/assets/js/user.js"></script>
    <?php endif; ?>
<?php endif; ?>

<!-- Script για κλείσιμο των alerts -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Κλείσιμο των alerts
        const closeButtons = document.querySelectorAll('.close-alert');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const alert = this.closest('.alert');
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        });
        
        // Αυτόματο κλείσιμο των alerts μετά από 5 δευτερόλεπτα
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Toggle του navbar στο κινητό
        const navbarToggler = document.getElementById('navbar-toggler');
        const navbarMenu = document.getElementById('navbar-menu');
        
        if (navbarToggler && navbarMenu) {
            navbarToggler.addEventListener('click', function() {
                navbarMenu.classList.toggle('active');
            });
        }
        
        // Toggle των dropdowns
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = this.nextElementSibling;
                dropdown.classList.toggle('show');
            });
        });
        
        // Κλείσιμο των dropdowns όταν κάνουμε κλικ έξω από αυτά
        document.addEventListener('click', function(e) {
            if (!e.target.matches('.dropdown-toggle')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(function(dropdown) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });
    });
</script>