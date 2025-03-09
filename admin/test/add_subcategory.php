<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

$error_message = '';
$success_message = '';

// Ανάκτηση των κατηγοριών για το dropdown
$categories_query = "SELECT id, name FROM test_categories ORDER BY name";
$categories_result = $mysqli->query($categories_query);

// Επεξεργασία φόρμας
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Ανάκτηση και καθαρισμός δεδομένων
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');

    log_debug("Attempting to add subcategory - Name: $name, Category ID: $category_id");

    // Έλεγχος υποχρεωτικών πεδίων
    if (empty($name) || $category_id <= 0) {
        $error_message = '🚨 Σφάλμα: Συμπληρώστε όλα τα υποχρεωτικά πεδία (Όνομα, Κατηγορία).';
        log_debug("Validation failed: Missing required fields (name or category_id)");
    } else {
        // Έλεγχος αν υπάρχει ήδη η υποκατηγορία
        $check_query = "SELECT COUNT(*) as count FROM test_subcategories WHERE name = ? AND test_category_id = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("si", $name, $category_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result['count'] > 0) {
            $error_message = '🚨 Σφάλμα: Η υποκατηγορία με αυτό το όνομα υπάρχει ήδη στην επιλεγμένη κατηγορία!';
            log_debug("Duplicate subcategory name: $name for category ID: $category_id");
        } else {
            // Εισαγωγή στη βάση δεδομένων
            $insert_query = "INSERT INTO test_subcategories (name, test_category_id, description, icon) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($insert_query);
            $stmt->bind_param("siss", $name, $category_id, $description, $icon);

            if ($stmt->execute()) {
                $success_message = '✅ Η υποκατηγορία προστέθηκε επιτυχώς!';
                log_debug("Subcategory added successfully: $name, Category ID: $category_id");
                
                // Προαιρετικά: καθαρισμός της φόρμας μετά από επιτυχή προσθήκη
                $name = '';
                $category_id = '';
                $description = '';
                $icon = '';
            } else {
                $error_message = '🚨 Σφάλμα κατά την προσθήκη: ' . $stmt->error;
                log_debug("SQL error inserting subcategory: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>

<main class="admin-container">
    <h1 class="admin-title">Προσθήκη Νέας Υποκατηγορίας</h1>
    
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Επιτυχία!</strong> <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Σφάλμα!</strong> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <div class="subscription-form-container">
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="name">Όνομα Υποκατηγορίας:</label>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($name ?? '') ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label for="category_id">Κατηγορία:</label>
                <select name="category_id" id="category_id" required class="form-control">
                    <option value="">-- Επιλέξτε Κατηγορία --</option>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?= $category['id'] ?>" <?= (isset($category_id) && $category_id == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Περιγραφή:</label>
                <textarea name="description" id="description" class="form-control"><?= htmlspecialchars($description ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="icon">Εικονίδιο (CSS κλάση ή όνομα αρχείου):</label>
                <input type="text" name="icon" id="icon" value="<?= htmlspecialchars($icon ?? '') ?>" class="form-control">
                <small class="form-text">Προαιρετικό. Μπορείτε να χρησιμοποιήσετε CSS κλάσεις (π.χ. "fa fa-book") ή ονόματα αρχείων (π.χ. "book.png").</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
                <a href="manage_subcategories.php" class="btn-secondary">🔙 Επιστροφή</a>
            </div>
        </form>
    </div>
</main>

<?php require_once 'includes/admin_footer.php'; ?>