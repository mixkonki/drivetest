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

<main class="admin-container">
    <h2 class="admin-title">📌 Διαχείριση Υποκατηγοριών</h2>

    <!-- 🔹 Πίνακας Υποκατηγοριών -->
    <table class="admin-table" id="subcategory-table">
        <thead>
            <tr>
                <th>Όνομα</th>
                <th>Κατηγορία</th>
                <th>Περιγραφή</th>
                <th>Εικονίδιο</th>
                <th>Δράσεις</th>
            </tr>
        </thead>
        <tbody id="subcategory-list">
            <!-- Οι υποκατηγορίες φορτώνονται μέσω JavaScript -->
        </tbody>
    </table>

    <button id="add-subcategory-btn" class="btn-primary">➕ Προσθήκη Νέας Υποκατηγορίας</button>
    <button onclick="window.location.href='../dashboard.php'" class="btn-secondary">🔙 Επιστροφή στη Διαχείριση</button>

    <!-- 🔹 Φόρμα για προσθήκη/επεξεργασία -->
    <div id="subcategory-form" style="display:none;">
        <h3 id="form-title">Προσθήκη Υποκατηγορίας</h3>
        <form id="subcategory-form-data">
            <input type="hidden" name="id" id="subcategory-id">
            <div class="form-group">
                <label for="subcategory-name">Όνομα:</label>
                <input type="text" id="subcategory-name" name="name" required>
            </div>
            <div class="form-group">
                <label for="subcategory-category">Κατηγορία:</label>
                <select id="subcategory-category" name="category_id" required>
                    <?php
                    $categories_query = "SELECT id, name FROM test_categories ORDER BY name ASC";
                    $categories_result = $mysqli->query($categories_query);
                    while ($cat = $categories_result->fetch_assoc()) {
                        echo "<option value='{$cat['id']}'>{$cat['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subcategory-description">Περιγραφή:</label>
                <textarea id="subcategory-description" name="description"></textarea>
            </div>
            <div class="form-group">
                <label for="subcategory-icon">Εικονίδιο (όνομα αρχείου):</label>
                <input type="text" id="subcategory-icon" name="icon" placeholder="π.χ. car.png">
            </div>
            <button type="submit" class="btn-primary">Αποθήκευση</button>
            <button type="button" id="cancel-subcategory" class="btn-secondary">Ακύρωση</button>
        </form>
    </div>
</main>

<script src="../assets/js/subcategory_manager.js" defer></script>

<?php require_once '../includes/admin_footer.php'; ?>