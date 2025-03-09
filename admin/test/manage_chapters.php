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

// Προσθήκη του ειδικού CSS για τη διαχείριση κεφαλαίων
$additional_css = '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/chapter_management.css">';
?>

<main class="admin-container">
    <div class="admin-section-header">
        <h2 class="admin-title">📚 Διαχείριση Κεφαλαίων</h2>
        <div class="admin-actions">
            <button id="add-chapter-btn" class="btn-primary">➕ Προσθήκη Νέου Κεφαλαίου</button>
            <a href="../dashboard.php" class="btn-secondary">🔙 Επιστροφή στο Dashboard</a>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <p><?= htmlspecialchars($_GET['success']) ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <p><?= htmlspecialchars($_GET['error']) ?></p>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="admin-table" id="chapter-table">
            <thead>
                <tr>
                    <th>Όνομα</th>
                    <th>Υποκατηγορία</th>
                    <th>Περιγραφή</th>
                    <th>Εικονίδιο</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody id="chapter-list">
                <!-- Τα κεφάλαια φορτώνονται μέσω JavaScript -->
            </tbody>
        </table>
    </div>
</main>

<script src="../assets/js/chapter_manager.js"></script>
<script src="../assets/js/chapter_upload.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>