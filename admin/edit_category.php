<?php
// edit_category.php (Ενημερωμένο)
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<p class='error-message'>🚨 Σφάλμα: Μη έγκυρο ID κατηγορίας.</p>");
}

$category_id = intval($_GET['id']);

// Ανάκτηση των στοιχείων της κατηγορίας
$query = "SELECT name, price, icon, description FROM subscription_categories WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    die("<p class='error-message'>🚨 Σφάλμα: Η κατηγορία δεν βρέθηκε.</p>");
}

// Διαχείριση υποβολής φόρμας
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $icon = trim($_POST['icon']);
    $description = trim($_POST['description'] ?? '');

    log_debug("Attempting to edit category $category_id - Name: $name, Price: $price, Icon: $icon, Description: $description");

    if (empty($name) || empty($price) || empty($icon)) {
        echo "<p class='error-message'>🚨 Σφάλμα: Συμπληρώστε όλα τα πεδία.</p>";
        log_debug("Validation failed: Missing required fields");
    } else {
        $iconPath = BASE_PATH . '/assets/images/' . basename($icon);
        if (!file_exists($iconPath)) {
            echo "<p class='error-message'>🚨 Σφάλμα: Το εικονίδιο δεν βρέθηκε!</p>";
            log_debug("Icon not found: $iconPath");
            exit();
        }

        // Ενημέρωση της βάσης δεδομένων
        $query = "UPDATE subscription_categories SET name = ?, price = ?, icon = ?, description = ? WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sdssi", $name, $price, $icon, $description, $category_id);

        if ($stmt->execute()) {
            log_debug("Category $category_id updated successfully");

            // Ενημέρωση αντίστοιχης κατηγορίας τεστ
            $test_query = "UPDATE test_categories SET name = ?, description = ? WHERE subscription_category_id = ?";
            $stmt_test = $mysqli->prepare($test_query);
            $stmt_test->bind_param("ssi", $name, $description, $category_id);
            $stmt_test->execute();
            $stmt_test->close();

            header("Location: admin_subscriptions.php?success=updated");
            exit();
        } else {
            echo "<p class='error-message'>🚨 Σφάλμα κατά την ενημέρωση: " . $stmt->error . "</p>";
            log_debug("SQL error updating category $category_id: " . $stmt->error);
        }
        $stmt->close();
    }
}
?>

<main class="admin-container">
    <h2 class="admin-title">✏️ Επεξεργασία Κατηγορίας</h2>

    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="name">Όνομα Κατηγορίας:</label>
            <input type="text" name="name" id="name" value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="price">Τιμή (€):</label>
            <input type="number" step="0.01" name="price" id="price" value="<?= htmlspecialchars($category['price']) ?>" required>
        </div>

        <div class="form-group">
            <label for="icon">Εικονίδιο (όνομα αρχείου):</label>
            <input type="text" name="icon" id="icon" value="<?= htmlspecialchars($category['icon']) ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Περιγραφή:</label>
            <textarea name="description" id="description"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
    </form>

    <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
</main>

<?php require_once 'includes/admin_footer.php'; ?>