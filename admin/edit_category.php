
<?php
ob_start();
// edit_category.php (Ενημερωμένο)
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

// Φόρτωση ειδικών CSS και JS για τη σελίδα
$additional_css = '<link rel="stylesheet" href="' . BASE_URL . '/admin/assets/css/category_form.css">';
$additional_js = '<script src="' . BASE_URL . '/admin/assets/js/category_upload.js"></script>';

require_once 'includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Επεξεργασία μηνυμάτων επιτυχίας/σφάλματος
$success_message = '';
$error_message = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = '🚨 Σφάλμα: Μη έγκυρο ID κατηγορίας.';
    log_debug("Invalid category ID provided: " . ($_GET['id'] ?? 'none'));
} else {
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
        $error_message = '🚨 Σφάλμα: Η κατηγορία δεν βρέθηκε.';
        log_debug("Category with ID $category_id not found");
    }

    // Δημιουργία του φακέλου upload αν δεν υπάρχει
    $upload_dir = BASE_PATH . '/assets/images/categories/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Διαχείριση υποβολής φόρμας
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $description = trim($_POST['description'] ?? '');
        $current_icon = $category['icon']; // Διατήρηση του τρέχοντος εικονιδίου

        log_debug("Attempting to edit category $category_id - Name: $name, Price: $price, Description: $description");

        if (empty($name) || empty($price)) {
            $error_message = '🚨 Σφάλμα: Συμπληρώστε όλα τα απαιτούμενα πεδία (Όνομα, Τιμή).';
            log_debug("Validation failed: Missing required fields (name or price)");
        } else {
            // Διαχείριση του αρχείου εικόνας
            $icon = $current_icon; // Προεπιλογή: διατήρηση του τρέχοντος εικονιδίου

            if (!empty($_FILES['icon_file']['name'])) {
                $file_name = $_FILES['icon_file']['name'];
                $file_tmp = $_FILES['icon_file']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Επιτρεπόμενες επεκτάσεις
                $allowed_ext = array("jpg", "jpeg", "png", "gif", "svg");
                
                if (in_array($file_ext, $allowed_ext)) {
                    // Δημιουργία μοναδικού ονόματος αρχείου για αποφυγή συγκρούσεων
                    $new_file_name = 'category_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $icon = 'categories/' . $new_file_name;
                        log_debug("New file uploaded successfully: $icon");
                    } else {
                        $error_message = '⚠️ Προσοχή: Πρόβλημα στο ανέβασμα του αρχείου. Διατηρείται το υπάρχον εικονίδιο.';
                        log_debug("Failed to upload file. Error: " . error_get_last()['message']);
                    }
                } else {
                    $error_message = '⚠️ Προσοχή: Μη επιτρεπόμενος τύπος αρχείου. Επιτρέπονται μόνο jpg, jpeg, png, gif, svg. Διατηρείται το υπάρχον εικονίδιο.';
                    log_debug("Invalid file type: $file_ext");
                }
            } elseif (!empty($_POST['icon_url'])) {
                // Αν δόθηκε URL εικόνας
                $icon_url = trim($_POST['icon_url']);
                
                // Έλεγχος αν το URL είναι σχετικό ή απόλυτο
                if (strpos($icon_url, 'http://') === 0 || strpos($icon_url, 'https://') === 0) {
                    // Απόλυτο URL - αποθηκεύουμε όπως είναι
                    $icon = $icon_url;
                    log_debug("Using external icon URL: $icon");
                } else {
                    // Σχετικό URL - προσθέτουμε το μονοπάτι των εικόνων αν δεν υπάρχει ήδη
                    if (strpos($icon_url, 'assets/images/') === 0) {
                        // Το URL αρχίζει ήδη με 'assets/images/'
                        $icon = $icon_url;
                    } else if (strpos($icon_url, 'images/') === 0) {
                        // Το URL αρχίζει με 'images/'
                        $icon = 'assets/' . $icon_url;
                    } else if (strpos($icon_url, 'categories/') === 0) {
                        // Το URL αρχίζει με 'categories/'
                        $icon = 'assets/images/' . $icon_url;
                    } else {
                        // Οποιοδήποτε άλλο σχετικό URL - προσθέτουμε ολόκληρο το μονοπάτι
                        $icon = 'assets/images/' . $icon_url;
                    }
                    log_debug("Using relative icon URL, converted to: $icon");
                }
            }

            // Έλεγχος αν υπάρχει ήδη κατηγορία με αυτό το όνομα (εκτός της τρέχουσας)
            $check_query = "SELECT COUNT(*) as count FROM subscription_categories WHERE name = ? AND id != ?";
            $stmt_check = $mysqli->prepare($check_query);
            $stmt_check->bind_param("si", $name, $category_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($result['count'] > 0) {
                $error_message = '🚨 Σφάλμα: Η κατηγορία με αυτό το όνομα υπάρχει ήδη!';
                log_debug("Duplicate category name: $name for different ID");
            } else {
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
                    $error_message = '🚨 Σφάλμα κατά την ενημέρωση: ' . $stmt->error;
                    log_debug("SQL error updating category $category_id: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}
ob_end_flush();
?>

<main class="admin-container">
    <h1 class="admin-title">✏️ Επεξεργασία Κατηγορίας</h1>

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

    <?php if (isset($category) && $category): ?>
    <div class="subscription-form-container">
        <form method="POST" class="admin-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Όνομα Κατηγορίας:</label>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($category['name']) ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label for="price">Τιμή (€):</label>
                <input type="number" step="0.01" name="price" id="price" value="<?= htmlspecialchars($category['price']) ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label>Εικονίδιο Κατηγορίας:</label>
                
                <div class="tab-controls">
                    <button type="button" class="tab-btn active" id="upload-tab-btn">Ανέβασμα Εικόνας</button>
                    <button type="button" class="tab-btn" id="url-tab-btn">URL Εικόνας</button>
                </div>
                
                <div class="tab-content" id="upload-tab">
                    <div class="upload-container">
                        <div class="upload-preview" id="icon-preview">
                            <?php
                            $icon_path = '';
                            if (!empty($category['icon'])) {
                                if (strpos($category['icon'], 'http://') === 0 || strpos($category['icon'], 'https://') === 0) {
                                    $icon_path = $category['icon'];
                                } else {
                                    $icon_path = BASE_URL . '/assets/images/' . $category['icon'];
                                }
                            } else {
                                $icon_path = BASE_URL . '/assets/images/default.png';
                            }
                            ?>
                            <img src="<?= htmlspecialchars($icon_path) ?>" alt="Προεπισκόπηση εικονιδίου" id="preview-image">
                        </div>
                        <div class="upload-controls">
                            <input type="file" name="icon_file" id="icon_file" class="file-input" accept="image/*">
                            <label for="icon_file" class="upload-btn">Επιλογή Εικόνας</label>
                            <small class="form-text">Τρέχον εικονίδιο: <?= htmlspecialchars($category['icon'] ?: 'default.png') ?></small>
                            <small class="form-text">Επιτρεπόμενοι τύποι: JPG, JPEG, PNG, GIF, SVG</small>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content hidden" id="url-tab">
                    <div class="url-preview-container">
                        <div class="url-preview-wrapper">
                            <img src="<?= htmlspecialchars($icon_path) ?>" alt="Προεπισκόπηση URL εικόνας" id="icon-url-preview">
                        </div>
                        <div class="url-input-wrapper">
                            <input type="text" name="icon_url" id="icon_url" placeholder="π.χ. category-icon.png ή categories/icon.png" class="form-control">
                            <small class="form-text">
                                Μπορείτε να εισάγετε:
                                <ul>
                                    <li>Όνομα αρχείου (π.χ. icon.png)</li>
                                    <li>Σχετικό μονοπάτι (π.χ. categories/icon.png)</li>
                                    <li>Πλήρες URL (π.χ. https://example.com/image.jpg)</li>
                                </ul>
                                <div>Τρέχον εικονίδιο: <?= htmlspecialchars($category['icon'] ?: 'default.png') ?></div>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Περιγραφή:</label>
                <textarea name="description" id="description" placeholder="Προαιρετική περιγραφή" class="form-control"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
                <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-danger">
        <strong>Σφάλμα!</strong> Η κατηγορία δεν βρέθηκε ή δεν προσδιορίστηκε έγκυρο ID.
    </div>
    <div class="form-actions">
        <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
    </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/admin_footer.php'; ?>