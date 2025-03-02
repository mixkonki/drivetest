<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<p class='error-message'>🚨 Σφάλμα: Μη έγκυρο ID διάρκειας.</p>");
}

$duration_id = intval($_GET['id']);

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Έλεγχος αν υπάρχουν ενεργές συνδρομές που χρησιμοποιούν αυτή τη διάρκεια
    $check_subscriptions_query = "SELECT COUNT(*) as count FROM subscriptions WHERE JSON_CONTAINS(durations, CAST(? AS JSON)) AND status = 'active'";
    $stmt_check = $mysqli->prepare($check_subscriptions_query);
    $stmt_check->bind_param("i", $duration_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        echo "<p class='error-message'>🚨 Σφάλμα: Δεν μπορείτε να διαγράψετε αυτή τη διάρκεια, καθώς υπάρχουν ενεργές συνδρομές!</p>";
        log_debug("Cannot delete duration ID $duration_id: Active subscriptions exist with duration");
        exit();
    }

    // Διαγραφή της διάρκειας από τον πίνακα subscription_durations
    $delete_duration_query = "DELETE FROM subscription_durations WHERE id = ?";
    $stmt_duration = $mysqli->prepare($delete_duration_query);
    $stmt_duration->bind_param("i", $duration_id);

    if ($stmt_duration->execute()) {
        log_debug("Duration ID $duration_id deleted successfully");
        header("Location: admin_subscriptions.php?success=deleted");
        exit();
    } else {
        echo "<p class='error-message'>🚨 Σφάλμα κατά τη διαγραφή: " . $stmt_duration->error . "</p>";
        log_debug("SQL error deleting duration $duration_id: " . $stmt_duration->error);
    }

    $stmt_duration->close();
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
        <main class="admin-container" role="main" aria-label="Επιβεβαίωση Διαγραφής Διάρκειας">
            <h2 class="admin-title" role="heading" aria-level="2">⚠️ Επιβεβαίωση Διαγραφής</h2>
            <p>Σίγουρα θέλετε να διαγράψετε αυτή τη διάρκεια;</p>
            <a href="delete_duration.php?id=<?= $duration_id ?>&confirm=yes" class="btn-primary" aria-label="Επιβεβαίωση Διαγραφής Διάρκειας">Ναι, Διαγραφή</a>
            <a href="admin_subscriptions.php" class="btn-secondary" aria-label="Επιστροφή χωρίς Διαγραφή">Όχι, Επιστροφή</a>
        </main>
    </body>
    </html>
    <?php
}
?>