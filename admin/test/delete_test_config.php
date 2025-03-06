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

// Λήψη του config_id από το query string
$config_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($config_id === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Απαιτείται αναγνωριστικό ρύθμισης.'
    ]);
    exit;
}

try {
    // Έλεγχος αν υπάρχουν τεστ που χρησιμοποιούν αυτή τη ρύθμιση
    $check_query = "SELECT COUNT(*) as count FROM tests WHERE config_id = ?";
    $check_stmt = $mysqli->prepare($check_query);
    
    if (!$check_stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $config_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count = $check_result->fetch_assoc()['count'];
    
    if ($count > 0) {
        throw new Exception("Δεν μπορείτε να διαγράψετε αυτή τη ρύθμιση καθώς χρησιμοποιείται από {$count} τεστ.");
    }
    
    // Διαγραφή της ρύθμισης
    $delete_query = "DELETE FROM test_configurations WHERE id = ?";
    $delete_stmt = $mysqli->prepare($delete_query);
    
    if (!$delete_stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος διαγραφής: " . $mysqli->error);
    }
    
    $delete_stmt->bind_param("i", $config_id);
    $result = $delete_stmt->execute();
    
    if (!$result) {
        throw new Exception("Σφάλμα κατά τη διαγραφή: " . $delete_stmt->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Η ρύθμιση διαγράφηκε επιτυχώς.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>