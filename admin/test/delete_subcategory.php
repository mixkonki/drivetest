<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<p class='error-message'>🚨 Σφάλμα: Μη έγκυρο ID υποκατηγορίας.</p>");
}

$subcategory_id = intval($_GET['id']);

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Έλεγχος αν υπάρχουν κεφάλαια που χρησιμοποιούν αυτήν την υποκατηγορία
    $check_chapters_query = "SELECT COUNT(*) as count FROM test_chapters WHERE subcategory_id = ?";
    $stmt_check = $mysqli->prepare($check_chapters_query);
    $stmt_check->bind_param("i", $subcategory_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        echo "<p class='error-message'>🚨 Σφάλμα: Δεν μπορείτε να διαγράψετε αυτή την υποκατηγορία, καθώς υπάρχουν κεφάλαια που την χρησιμοποιούν!</p>";
        log_debug("Cannot delete subcategory ID $subcategory_id: Chapters exist with this subcategory");
        echo "<a href='manage_subcategories.php' class='btn-secondary'>Επιστροφή</a>";
        exit();
    }

    // Έλεγχος αν υπάρχουν ερωτήσεις που χρησιμοποιούν αυτήν την υποκατηγορία
    $check_questions_query = "SELECT COUNT(*) as count FROM questions WHERE chapter_id IN (SELECT id FROM test_chapters WHERE subcategory_id = ?)";
    $stmt_check = $mysqli->prepare($check_questions_query);
    $stmt_check->bind_param("i", $subcategory_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        echo "<p class='error-message'>🚨 Σφάλμα: Δεν μπορείτε να διαγράψετε αυτή την υποκατηγορία, καθώς υπάρχουν ερωτήσεις που την χρησιμοποιούν!</p>";
        log_debug("Cannot delete subcategory ID $subcategory_id: Questions exist with this subcategory");
        echo "<a href='manage_subcategories.php' class='btn-secondary'>Επιστροφή</a>";
        exit();
    }

    // Διαγραφή της υποκατηγορίας
    $delete_subcategory_query = "DELETE FROM test_subcategories WHERE id = ?";
    $stmt_subcategory = $mysqli->prepare($delete_subcategory_query);
    $stmt_subcategory->bind_param("i", $subcategory_id);

    if ($stmt_subcategory->execute()) {
        log_debug("Subcategory ID $subcategory_id deleted successfully");
        header("Location: manage_subcategories.php?success=deleted");
        exit();
    } else {
        echo "<p class='error-message'>🚨 Σφάλμα κατά τη διαγραφή: " . $stmt_subcategory->error . "</p>";
        log_debug("SQL error deleting subcategory $subcategory_id: " . $stmt_subcategory->error);
        echo "<a href='manage_subcategories.php' class='btn-secondary'>Επιστροφή</a>";
    }

    $stmt_subcategory->close();
} else {
    // Εμφάνιση επιβεβαίωσης διαγραφής
    ?>
    <!DOCTYPE html>
    <html lang="el">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Επιβεβαίωση Διαγραφής</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_unified.css">
    </head>
    <body>
        <main class="admin-container" role="main" aria-label="Επιβεβαίωση Διαγραφής Υποκατηγορίας">
            <h2 class="admin-title" role="heading" aria-level="2">⚠️ Επιβεβαίωση Διαγραφής</h2>
            <p>Σίγουρα θέλετε να διαγράψετε αυτή την υποκατηγορία;</p>
            <div class="form-actions">
                <a href="delete_subcategory.php?id=<?= $subcategory_id ?>&confirm=yes" class="btn-primary" aria-label="Επιβεβαίωση Διαγραφής Υποκατηγορίας">Ναι, Διαγραφή</a>
                <a href="manage_subcategories.php" class="btn-secondary" aria-label="Επιστροφή χωρίς Διαγραφή">Όχι, Επιστροφή</a>
            </div>
        </main>
    </body>
    </html>
    <?php
}
?>