<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

// Ορισμός τίτλου σελίδας
$page_title = 'Προσθήκη Νέας Κατηγορίας Συνδρομής';
// Flag για φόρτωση κοινού CSS φορμών
$load_form_common_css = true;


require_once 'includes/admin_header.php';



// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Επεξεργασία μηνυμάτων επιτυχίας/σφάλματος
$success_message = '';
$error_message = '';

// Δημιουργία του φακέλου upload αν δεν υπάρχει
$upload_dir = BASE_PATH . '/assets/images/categories/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $description = trim($_POST['description'] ?? '');
    $icon = '';

    log_debug("Attempting to add category - Name: $name, Price: $price, Description: $description");

    if (empty($name) || empty($price)) {
        $error_message = '🚨 Σφάλμα: Συμπληρώστε όλα τα απαιτούμενα πεδία (Όνομα, Τιμή).';
        log_debug("Validation failed: Missing required fields (name or price)");
    } else {
        // Διαχείριση του αρχείου εικόνας
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
                    log_debug("File uploaded successfully: $icon");
                } else {
                    $error_message = '⚠️ Προσοχή: Πρόβλημα στο ανέβασμα του αρχείου. Χρησιμοποιείται προεπιλεγμένο εικονίδιο.';
                    log_debug("Failed to upload file. Error: " . error_get_last()['message']);
                    $icon = 'default.png';
                }
            } else {
                $error_message = '⚠️ Προσοχή: Μη επιτρεπόμενος τύπος αρχείου. Επιτρέπονται μόνο jpg, jpeg, png, gif, svg. Χρησιμοποιείται προεπιλεγμένο εικονίδιο.';
                log_debug("Invalid file type: $file_ext");
                $icon = 'default.png';
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
        } else {
            // Αν δεν δόθηκε ούτε αρχείο ούτε URL
            $icon = 'default.png';
            log_debug("No icon provided, using default: $icon");
        }

        // Έλεγχος αν υπάρχει ήδη η κατηγορία
        $check_query = "SELECT COUNT(*) as count FROM subscription_categories WHERE name = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result['count'] > 0) {
            $error_message = '🚨 Σφάλμα: Η κατηγορία με αυτό το όνομα υπάρχει ήδη!';
            log_debug("Duplicate category name: $name");
        } else {
            // Εισαγωγή στη βάση δεδομένων
            $query = "INSERT INTO subscription_categories (name, price, icon, description) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("sdss", $name, $price, $icon, $description);

            if ($stmt->execute()) {
                $new_subscription_id = $stmt->insert_id;
                log_debug("Category added successfully with ID: $new_subscription_id");

                // Έλεγχος αν υπάρχει default διάρκεια (ID = 1)
                $default_duration_id = 1;
                $check_duration_query = "SELECT COUNT(*) as count FROM subscription_durations WHERE id = ?";
                $stmt_duration = $mysqli->prepare($check_duration_query);
                $stmt_duration->bind_param("i", $default_duration_id);
                $stmt_duration->execute();
                $duration_result = $stmt_duration->get_result()->fetch_assoc();
                $stmt_duration->close();

                if ($duration_result['count'] > 0) {
                    $default_user_id = $_SESSION['user_id']; // Χρήστης admin ως default
                    $months = 1; // Default διάρκεια 1 μήνα

                    // Δημιουργία JSON για categories και durations
                    $categories_json = json_encode([$new_subscription_id]); // Π.χ. [1]
                    $durations_json = json_encode([$default_duration_id => $months]); // Π.χ. {"1": 1}

                    // Εισαγωγή στη βάση δεδομένων με JSON
                    $query_subscription = "INSERT INTO subscriptions (user_id, subscription_type, status, expiry_date, created_at, start_date, categories, durations) VALUES (?, 'monthly', 'active', DATE_ADD(CURDATE(), INTERVAL ? MONTH), NOW(), CURDATE(), ?, ?)";
                    $stmt_subscription = $mysqli->prepare($query_subscription);
                    $stmt_subscription->bind_param("iiss", $default_user_id, $months, $categories_json, $durations_json);

                    if ($stmt_subscription->execute()) {
                        log_debug("Default subscription created for category ID: $new_subscription_id with categories: $categories_json, durations: $durations_json");
                    } else {
                        $error_message = '⚠️ Προσοχή: Δεν δημιουργήθηκε default συνδρομή - ' . $stmt_subscription->error;
                        log_debug("Failed to create default subscription: " . $stmt_subscription->error);
                    }
                    $stmt_subscription->close();
                } else {
                    $error_message = '⚠️ Προσοχή: Δεν υπάρχει διάρκεια με ID 1 για default συνδρομή.';
                    log_debug("Default duration ID 1 not found");
                }

                header("Location: admin_subscriptions.php?success=added");
                exit();
            } else {
                $error_message = '🚨 Σφάλμα κατά την εισαγωγή στη βάση: ' . $stmt->error;
                log_debug("SQL error inserting category: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>

<main class="admin-container">
    <h1 class="admin-title">➕ Προσθήκη Νέας Κατηγορίας Συνδρομής</h1>
    
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
        <form method="POST" class="admin-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Όνομα Κατηγορίας:</label>
                <input type="text" name="name" id="name" required class="form-control">
            </div>

            <div class="form-group">
                <label for="price">Τιμή (€):</label>
                <input type="number" step="0.01" name="price" id="price" required class="form-control">
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
                            <img src="<?= BASE_URL ?>/assets/images/default.png" alt="Προεπισκόπηση εικονιδίου" id="preview-image">
                        </div>
                        <div class="upload-controls">
                            <input type="file" name="icon_file" id="icon_file" class="file-input" accept="image/*">
                            <label for="icon_file" class="upload-btn">Επιλογή Εικόνας</label>
                            <small class="form-text">Επιτρεπόμενοι τύποι: JPG, JPEG, PNG, GIF, SVG</small>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content hidden" id="url-tab">
                    <div class="url-preview-container">
                        <div class="url-preview-wrapper">
                            <img src="<?= BASE_URL ?>/assets/images/default.png" alt="Προεπισκόπηση URL εικόνας" id="icon-url-preview">
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
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Περιγραφή:</label>
                <textarea name="description" id="description" placeholder="Προαιρετική περιγραφή" class="form-control"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
                <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
            </div>
        </form>
    </div>
</main>

<?php require_once 'includes/admin_footer.php'; ?>