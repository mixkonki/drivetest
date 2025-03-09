<?php
// Ξεκινάμε το output buffering για την αποφυγή του "headers already sent" error
ob_start();

require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Έλεγχος αν έχει οριστεί έγκυρο ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = '🚨 Σφάλμα: Μη έγκυρο ID κατηγορίας.';
    log_debug("Invalid category ID provided: " . ($_GET['id'] ?? 'none'));
    
    // Ανακατεύθυνση στη σελίδα διαχείρισης συνδρομών με μήνυμα σφάλματος
    header("Location: admin_subscriptions.php?error=" . urlencode($error_message));
    exit();
}

$category_id = intval($_GET['id']);

// Ανάκτηση των στοιχείων της κατηγορίας για εμφάνιση
$query = "SELECT name, price, icon FROM subscription_categories WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    $error_message = '🚨 Σφάλμα: Η κατηγορία δεν βρέθηκε.';
    log_debug("Category with ID $category_id not found");
    header("Location: admin_subscriptions.php?error=" . urlencode($error_message));
    exit();
}

// Επιβεβαίωση διαγραφής
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    log_debug("Confirming deletion of category ID: $category_id");
    
    // Έλεγχος αν υπάρχουν ενεργές συνδρομές που χρησιμοποιούν αυτή την κατηγορία
    $check_subscriptions_query = "SELECT COUNT(*) as count FROM subscriptions WHERE JSON_CONTAINS(categories, CAST(? AS JSON)) AND status = 'active'";
    $stmt_check = $mysqli->prepare($check_subscriptions_query);
    $stmt_check->bind_param("i", $category_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result['count'] > 0) {
        log_debug("Cannot delete category ID $category_id: Active subscriptions exist with category");
        
        // Φόρτωση του header για εμφάνιση του σφάλματος
        require_once 'includes/admin_header.php';
        echo "<div class='admin-container'>";
        echo "<p class='error-message'>🚨 Σφάλμα: Δεν μπορείτε να διαγράψετε αυτή την κατηγορία, καθώς υπάρχουν ενεργές συνδρομές!</p>";
        echo "<div class='form-actions'>";
        echo "<a href='admin_subscriptions.php' class='btn-secondary'><i class='action-icon'>🔙</i> Επιστροφή</a>";
        echo "</div>";
        echo "</div>";
        
        require_once 'includes/admin_footer.php';
        ob_end_flush();
        exit();
    }

    // Διαγραφή από τον πίνακα test_categories
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
        
        // Ανακατεύθυνση στη σελίδα διαχείρισης συνδρομών με μήνυμα επιτυχίας
        header("Location: admin_subscriptions.php?success=deleted");
        $stmt_subscription->close();
        exit();
    } else {
        log_debug("SQL error deleting category $category_id: " . $stmt_subscription->error);
        
        // Φόρτωση του header για εμφάνιση του σφάλματος
        require_once 'includes/admin_header.php';
        echo "<div class='admin-container'>";
        echo "<p class='error-message'>🚨 Σφάλμα κατά τη διαγραφή: " . $stmt_subscription->error . "</p>";
        echo "<div class='form-actions'>";
        echo "<a href='admin_subscriptions.php' class='btn-secondary'><i class='action-icon'>🔙</i> Επιστροφή</a>";
        echo "</div>";
        echo "</div>";
        
        require_once 'includes/admin_footer.php';
        $stmt_subscription->close();
        ob_end_flush();
        exit();
    }
} else {
    // Φόρτωση του header για την σελίδα επιβεβαίωσης
    require_once 'includes/admin_header.php';
    
    // Προετοιμασία της εικόνας της κατηγορίας
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
    
    // Εμφάνιση επιβεβαίωσης διαγραφής με τα στοιχεία της κατηγορίας
    ?>
    <main class="admin-container" role="main" aria-label="Επιβεβαίωση Διαγραφής Κατηγορίας">
        <div class="confirmation-container">
            <div class="confirmation-header">
                <h2 class="admin-title">⚠️ Επιβεβαίωση Διαγραφής</h2>
                <div class="confirmation-icon">
                    <span class="warning-icon">❗</span>
                </div>
            </div>
            
            <div class="confirmation-content">
                <div class="category-preview">
                    <div class="category-icon-container">
                        <img src="<?= htmlspecialchars($icon_path) ?>" alt="Εικονίδιο <?= htmlspecialchars($category['name']) ?>" class="category-icon">
                    </div>
                    <div class="category-details">
                        <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                        <div class="category-price"><?= htmlspecialchars($category['price']) ?> €</div>
                    </div>
                </div>
                
                <p class="confirmation-message">
                    Πρόκειται να διαγράψετε την κατηγορία <strong><?= htmlspecialchars($category['name']) ?></strong>.
                </p>
                <p class="confirmation-warning">
                    Η ενέργεια αυτή δεν μπορεί να αναιρεθεί. Θα διαγραφούν επίσης όλες οι σχετικές εγγραφές στις test_categories. 
                    Αν υπάρχουν ενεργές συνδρομές που χρησιμοποιούν αυτή την κατηγορία, η διαγραφή δεν θα επιτραπεί.
                </p>
                <div class="confirmation-question">
                    Είστε σίγουροι ότι θέλετε να προχωρήσετε;
                </div>
            </div>
            
            <div class="confirmation-actions">
                <a href="delete_category.php?id=<?= $category_id ?>&confirm=yes" class="btn-danger" aria-label="Επιβεβαίωση Διαγραφής Κατηγορίας">
                    <i class="action-icon">🗑️</i> Ναι, Διαγραφή
                </a>
                <a href="admin_subscriptions.php" class="btn-secondary" aria-label="Επιστροφή χωρίς Διαγραφή">
                    <i class="action-icon">🔙</i> Όχι, Επιστροφή
                </a>
            </div>
        </div>
    </main>

    <style>
        /* Στυλ για τη σελίδα επιβεβαίωσης */
        .confirmation-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        
        .confirmation-header {
            margin-bottom: 20px;
            position: relative;
        }
        
        .confirmation-icon {
            margin: 20px auto;
            width: 80px;
            height: 80px;
            background-color: #fff5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #ffebeb;
        }
        
        .warning-icon {
            font-size: 40px;
            color: var(--danger-color);
        }
        
        .confirmation-content {
            margin-bottom: 30px;
        }
        
        .category-preview {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius-md);
            margin-bottom: 20px;
            gap: 15px;
            justify-content: center;
        }
        
        .category-icon-container {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-icon {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .category-details {
            text-align: left;
        }
        
        .category-name {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: var(--primary-color);
        }
        
        .category-price {
            font-weight: bold;
            font-size: 16px;
            color: var(--text-dark);
        }
        
        .confirmation-message {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--text-dark);
        }
        
        .confirmation-message strong {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .confirmation-warning {
            background-color: #fff5f5;
            padding: 12px;
            border-radius: var(--border-radius-md);
            border-left: 4px solid var(--danger-color);
            color: var(--text-dark);
            text-align: left;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .confirmation-question {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .confirmation-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .btn-danger {
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius-md);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
            font-size: 16px;
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .action-icon {
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .confirmation-actions {
                flex-direction: column;
            }
            
            .btn-danger,
            .btn-secondary {
                width: 100%;
            }
        }
    </style>
    <?php
    require_once 'includes/admin_footer.php';
    ob_end_flush();
}
?>