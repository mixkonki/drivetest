<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

header('Content-Type: application/json');

// Έλεγχος αν η μέθοδος είναι POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Μη έγκυρη μέθοδος αιτήματος.'
    ]);
    exit;
}

// Λήψη του test_id από το query string
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($test_id === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Απαιτείται αναγνωριστικό τεστ.'
    ]);
    exit;
}

try {
    // Έλεγχος αν υπάρχουν αποτελέσματα τεστ που χρησιμοποιούν αυτό το τεστ
    $check_query = "SELECT COUNT(*) as count FROM test_results WHERE test_id = ?";
    $check_stmt = $mysqli->prepare($check_query);
    
    if (!$check_stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $test_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_assoc()['count'];
    
    if ($count > 0) {
        throw new Exception("Δεν μπορείτε να διαγράψετε αυτό το τεστ καθώς έχει ήδη χρησιμοποιηθεί από {$count} χρήστες.");
    }
    
    // Διαγραφή των ερωτήσεων του τεστ
    $delete_questions_query = "DELETE FROM test_generation_questions WHERE test_id = ?";
    $delete_questions_stmt = $mysqli->prepare($delete_questions_query);
    
    if (!$delete_questions_stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος διαγραφής ερωτήσεων: " . $mysqli->error);
    }
    
    $delete_questions_stmt->bind_param("i", $test_id);
    $delete_questions_stmt->execute();
    
    // Διαγραφή του τεστ
    $delete_test_query = "DELETE FROM test_generation WHERE id = ?";
    $delete_test_stmt = $mysqli->prepare($delete_test_query);
    
    if (!$delete_test_stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος διαγραφής τεστ: " . $mysqli->error);
    }
    
    $delete_test_stmt->bind_param("i", $test_id);
    $result = $delete_test_stmt->execute();
    
    if (!$result) {
        throw new Exception("Σφάλμα κατά τη διαγραφή: " . $delete_test_stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Το τεστ διαγράφηκε επιτυχώς.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>