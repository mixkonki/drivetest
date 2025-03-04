<?php
// generate_test.php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Λήψη παραμέτρων
$test_type = $_GET['test_type'] ?? 'chapter';
$category_id = intval($_GET['category_id'] ?? 0);
$chapter_id = intval($_GET['chapter_id'] ?? 0);
$question_count = intval($_GET['question_count'] ?? 20);
$time_limit = intval($_GET['time_limit'] ?? 0); // 0 = χωρίς όριο
$user_id = $_SESSION['user_id'] ?? null;

// Έλεγχος πρόσβασης
if (!$user_id || !hasActiveSubscription($category_id)) {
    die("Δεν έχετε πρόσβαση σε αυτή την κατηγορία τεστ!");
}

// Φόρτωση ερωτήσεων ανάλογα με τον τύπο τεστ
$questions = [];
switch ($test_type) {
    case 'chapter':
        if (!$chapter_id) {
            die("Παρακαλώ επιλέξτε κεφάλαιο!");
        }
        $query = "SELECT q.* FROM questions q 
                  WHERE q.chapter_id = ? 
                  ORDER BY RAND() 
                  LIMIT ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $chapter_id, $question_count);
        break;
        
    case 'random':
        $query = "SELECT q.* FROM questions q 
                  JOIN test_chapters c ON q.chapter_id = c.id
                  JOIN test_subcategories s ON c.subcategory_id = s.id
                  WHERE s.test_category_id = ? 
                  ORDER BY RAND() 
                  LIMIT ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $category_id, $question_count);
        break;
        
    case 'simulation':
        // Προσομοίωση: συγκεκριμένος αριθμός ερωτήσεων από κάθε κεφάλαιο
        $questions = getSimulationQuestions($mysqli, $category_id, $question_count);
        break;
        
    case 'difficult':
        $query = "SELECT q.* FROM questions q 
                  JOIN test_chapters c ON q.chapter_id = c.id
                  JOIN test_subcategories s ON c.subcategory_id = s.id
                  LEFT JOIN test_results_answers ra ON q.id = ra.question_id AND ra.user_id = ?
                  WHERE s.test_category_id = ? AND (ra.is_correct = 0 OR ra.id IS NULL)
                  GROUP BY q.id
                  ORDER BY RAND() 
                  LIMIT ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("iii", $user_id, $category_id, $question_count);
        break;
        
    default:
        die("Μη έγκυρος τύπος τεστ!");
}

// Εκτέλεση του ερωτήματος και φόρτωση των ερωτήσεων
if ($test_type !== 'simulation') {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
}

// Αν δεν υπάρχουν ερωτήσεις
if (empty($questions)) {
    die("Δεν υπάρχουν διαθέσιμες ερωτήσεις για το τεστ!");
}

// Αποθήκευση του τεστ στη συνεδρία
$_SESSION['current_test'] = [
    'type' => $test_type,
    'category_id' => $category_id,
    'chapter_id' => $chapter_id,
    'questions' => $questions,
    'total_questions' => count($questions),
    'time_limit' => $time_limit,
    'start_time' => time(),
    'answers' => []
];

// Ανακατεύθυνση στη σελίδα του τεστ
header("Location: test.php");
exit();

// Βοηθητική συνάρτηση για τεστ προσομοίωσης
function getSimulationQuestions($mysqli, $category_id, $total_count) {
    $questions = [];
    
    // Βρίσκουμε όλα τα κεφάλαια της κατηγορίας
    $query = "SELECT c.id FROM test_chapters c
              JOIN test_subcategories s ON c.subcategory_id = s.id
              WHERE s.test_category_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $chapters = [];
    while ($row = $result->fetch_assoc()) {
        $chapters[] = $row['id'];
    }
    $stmt->close();
    
    // Υπολογίζουμε πόσες ερωτήσεις θα πάρουμε από κάθε κεφάλαιο
    $question_per_chapter = max(1, intval($total_count / count($chapters)));
    
    // Παίρνουμε τυχαίες ερωτήσεις από κάθε κεφάλαιο
    foreach ($chapters as $chapter_id) {
        $query = "SELECT * FROM questions WHERE chapter_id = ? ORDER BY RAND() LIMIT ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $chapter_id, $question_per_chapter);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
            if (count($questions) >= $total_count) {
                break 2; // Βγαίνουμε από τους βρόχους όταν φτάσουμε το όριο
            }
        }
        $stmt->close();
    }
    
    return $questions;
}
?>