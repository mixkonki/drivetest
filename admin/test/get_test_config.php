<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

header('Content-Type: application/json');

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
    // Ανάκτηση των στοιχείων της ρύθμισης
    $query = "SELECT * FROM test_configurations WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $config_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();
    
    if (!$config) {
        throw new Exception("Η ρύθμιση δεν βρέθηκε.");
    }
    
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>