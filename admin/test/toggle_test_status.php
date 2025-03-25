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

// Λήψη του test_id και της νέας κατάστασης από το query string
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$new_status = isset($_GET['status']) ? $_GET['status'] : '';

if ($test_id === 0 || ($new_status !== 'active' && $new_status !== 'inactive')) {
    echo json_encode([
        'success' => false, 
        'message' => 'Απαιτείται έγκυρο αναγνωριστικό τεστ και κατάσταση.'
    ]);
    exit;
}

try {
    // Ενημέρωση της κατάστασης του τεστ
    $update_query = "UPDATE test_generation SET status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $mysqli->prepare($update_query);
    
    if (!$update_stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
    }
    
    $update_stmt->bind_param("si", $new_status, $test_id);
    $result = $update_stmt->execute();
    
    if (!$result) {
        throw new Exception("Σφάλμα κατά την ενημέρωση: " . $update_stmt->error);
    }
    
    $status_text = $new_status === 'active' ? 'ενεργοποιήθηκε' : 'απενεργοποιήθηκε';
    
    echo json_encode([
        'success' => true,
        'message' => "Το τεστ $status_text επιτυχώς."
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>