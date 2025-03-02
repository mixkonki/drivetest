<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $icon = trim($_POST['icon'] ?? ''); // Προαιρετικό, κενό αν δεν δοθεί
    $description = trim($_POST['description'] ?? '');

    log_debug("Attempting to add category - Name: $name, Price: $price, Icon: $icon, Description: $description");

    if (empty($name) || empty($price)) {
        echo "<p class='error-message'>🚨 Σφάλμα: Συμπληρώστε όλα τα απαιτούμενα πεδία (Όνομα, Τιμή).</p>";
        log_debug("Validation failed: Missing required fields (name or price)");
    } else {
        // Αν δεν δοθεί εικονίδιο, χρησιμοποίησε προκαθορισμένο
        if (empty($icon)) {
            $icon = 'default.png'; // Προκαθορισμένο εικονίδιο
            log_debug("No icon provided, using default: $icon");
        } else {
            $iconPath = BASE_PATH . '/assets/images/' . basename($icon);
            if (!file_exists($iconPath)) {
                echo "<p class='warning-message'>⚠️ Προσοχή: Το εικονίδιο '$icon' δεν βρέθηκε, χρησιμοποιείται προκαθορισμένο εικονίδιο (default.png).</p>";
                $icon = 'default.png'; // Χρήση προκαθορισμένου εικονιδίου αν δεν βρεθεί το αρχείο
                log_debug("Icon not found: $iconPath, using default: $icon");
            }
        }

        // Έλεγχος αν υπάρχει ήδη η κατηγορία
        $check_query = "SELECT COUNT(*) as count FROM subscription_categories WHERE name = ?";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result['count'] > 0) {
            echo "<p class='error-message'>🚨 Σφάλμα: Η κατηγορία με αυτό το όνομα υπάρχει ήδη!</p>";
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
                        echo "<p class='warning-message'>⚠️ Προσοχή: Δεν δημιουργήθηκε default συνδρομή - " . $stmt_subscription->error . "</p>";
                        log_debug("Failed to create default subscription: " . $stmt_subscription->error);
                    }
                    $stmt_subscription->close();
                } else {
                    echo "<p class='warning-message'>⚠️ Προσοχή: Δεν υπάρχει διάρκεια με ID 1 για default συνδρομή.</p>";
                    log_debug("Default duration ID 1 not found");
                }

                header("Location: admin_subscriptions.php?success=added");
                exit();
            } else {
                echo "<p class='error-message'>🚨 Σφάλμα κατά την εισαγωγή στη βάση: " . $stmt->error . "</p>";
                log_debug("SQL error inserting category: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>

<main class="admin-container">
    <h2 class="admin-title">➕ Προσθήκη Νέας Κατηγορίας</h2>

    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="name">Όνομα Κατηγορίας:</label>
            <input type="text" name="name" id="name" required>
        </div>

        <div class="form-group">
            <label for="price">Τιμή (€):</label>
            <input type="number" step="0.01" name="price" id="price" required>
        </div>

        <div class="form-group">
            <label for="icon">Εικονίδιο (όνομα αρχείου, προαιρετικό):</label>
            <input type="text" name="icon" id="icon" placeholder="π.χ. car.png">
        </div>

        <div class="form-group">
            <label for="description">Περιγραφή:</label>
            <textarea name="description" id="description" placeholder="Προαιρετική περιγραφή"></textarea>
        </div>

        <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
    </form>

    <a href="admin_subscriptions.php" class="btn-secondary">🔙 Επιστροφή</a>
</main>

<?php require_once 'includes/admin_footer.php'; ?>