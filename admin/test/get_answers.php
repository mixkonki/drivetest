<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

header('Content-Type: application/json');

// Λήψη του question_id από το query string
$question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;

if ($question_id === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Απαιτείται αναγνωριστικό ερώτησης.'
    ]);
    exit;
}

try {
    // Ανάκτηση των απαντήσεων για τη συγκεκριμένη ερώτηση
    $query = "SELECT id, answer_text, is_correct, answer_media 
              FROM test_answers 
              WHERE question_id = ?
              ORDER BY id ASC";
    
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Σφάλμα προετοιμασίας ερωτήματος: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $answers = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'answers' => $answers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>