<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

header('Content-Type: application/json');

// Λήψη του category_id από το query string
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if ($category_id === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Απαιτείται αναγνωριστικό κατηγορίας.'
    ]);
    exit;
}

try {
    // Ανάκτηση των κεφαλαίων για τη συγκεκριμένη κατηγορία
    $query = "SELECT c.id, c.name, c.description 
              FROM test_chapters c 
              JOIN test_subcategories s ON c.subcategory_id = s.id 
              WHERE s.test_category_id = ? 
              ORDER BY c.name ASC";
    
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $chapters = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'chapters' => $chapters
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>