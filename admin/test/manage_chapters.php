<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// 🔒 Έλεγχος αν ο χρήστης είναι διαχειριστής
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}
?>

<h2>📌 Διαχείριση Κεφαλαίων Τεστ</h2>

<!-- 🔹 Πίνακας Κεφαλαίων -->
<table border="1" id="chapter-table">
    <thead>
        <tr>
            <th>Όνομα</th>
            <th>Υποκατηγορία</th>
            <th>Περιγραφή</th>
            <th>Δράσεις</th>
        </tr>
    </thead>
    <tbody id="chapter-list">
        <!-- Τα κεφάλαια φορτώνονται μέσω JavaScript -->
    </tbody>
</table>

<button id="add-chapter-btn" class="btn-primary">➕ Προσθήκη Νέου Κεφαλαίου</button>
<button onclick="window.location.href='../dashboard.php'" class="btn-secondary">🔙 Επιστροφή στη Διαχείριση</button>

<!-- 🔹 Σωστή τοποθέτηση JavaScript -->
<script src="../assets/js/chapter_manager.js" defer></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    loadSubcategories();
    loadChapters(); // 🔹 Βεβαιώσου ότι φορτώνονται και τα κεφάλαια
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>