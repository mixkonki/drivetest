<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<p class='error-message'>🚨 Σφάλμα: Μη έγκυρο ID κατηγορίας.</p>");
}

$category_id = intval($_GET['id']);

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Έλεγχος αν υπάρχουν ενεργές συνδρομές που χρησιμοποιούν αυτή την κατηγορία
    $check_subscriptions_query = "SELECT COUNT(*) as count FROM subscriptions WHERE JSON_CONTAINS(categories, CAST(? AS JSON)) AND status = 'active'";
    $stmt_check = $mysqli->prepare($check_subscriptions_query);
    $stmt_check->bind_param("i", $category_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        echo "<p class='error-message'>🚨 Σφάλμα: Δεν μπορείτε να διαγράψετε αυτή την κατηγορία, καθώς υπάρχουν ενεργές συνδρομές!</p>";
        log_debug("Cannot delete category ID $category_id: Active subscriptions exist with category");
        exit();
    }

    // Διαγραφή πρώτα από τον πίνακα test_categories
    $delete_test_category_query = "DELETE FROM test_categories WHERE subscription_category_id = ?";
    $stmt_test = $mysqli->prepare($delete_test_category_query);
    $stmt_test->bind_param("i", $category_id);
    $stmt_test->execute();
    $stmt_test->close();

    // Διαγραφή της κατηγορίας από τον πίνακα subscription_categories
    $delete_subscription_query = "DELETE FROM subscription_categories WHERE id = ?";
    $stmt_subscription = $mysqli->prepare($delete_subscription_query);
    $stmt_subscription->bind_param("i", $category_id);

    if ($stmt_subscription->execute()) {
        log_debug("Category ID $category_id deleted successfully");
        header("Location: admin_subscriptions.php?success=deleted");
        exit();
    } else {
        echo "<p class='error-message'>🚨 Σφάλμα κατά τη διαγραφή: " . $stmt_subscription->error . "</p>";
        log_debug("SQL error deleting category $category_id: " . $stmt_subscription->error);
    }

    $stmt_subscription->close();
} else {
    // Εμφάνιση επιβεβαίωσης διαγραφής
    ?>
    <!DOCTYPE html>
    <html lang="el">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Επιβεβαίωση Διαγραφής</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_styles.css">
    </head>
    <body>
        <main class="admin-container">
            <h2 class="admin-title">⚠️ Επιβεβαίωση Διαγραφής</h2>
            <p>Σίγουρα θέλετε να διαγράψετε αυτή την κατηγορία;</p>
            <a href="delete_category.php?id=<?= $category_id ?>&confirm=yes" class="btn-primary">Ναι, Διαγραφή</a>
            <a href="admin_subscriptions.php" class="btn-secondary">Όχι, Επιστροφή</a>
        </main>
    </body>
    </html>
    <?php
}
?>