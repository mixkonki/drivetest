<?php
// Διαδρομή: /test/generate_test.php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος εξουσιοδότησης
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

// Λήψη παραμέτρων
$test_type = $_GET['test_type'] ?? 'random';
$category_id = intval($_GET['category_id'] ?? 0);
$chapter_id = intval($_GET['chapter_id'] ?? 0);
$question_count = intval($_GET['question_count'] ?? 20);
$time_limit = intval($_GET['time_limit'] ?? 0); // 0 = χωρίς όριο
$user_id = $_SESSION['user_id'];

// Έλεγχος έγκυρων παραμέτρων
if ($category_id <= 0) {
    $_SESSION['error_message'] = "Παρακαλώ επιλέξτε έγκυρη κατηγορία!";
    header("Location: start.php");
    exit();
}

// Έλεγχος πρόσβασης στην κατηγορία
$access_query = "
    SELECT COUNT(*) as has_access
    FROM subscriptions s 
    WHERE s.user_id = ? 
      AND s.status = 'active'
      AND JSON_CONTAINS(s.categories, CAST(? AS JSON))
      AND NOW() <= s.expiry_date
";
$stmt = $mysqli->prepare($access_query);
$stmt->bind_param("ii", $user_id, $category_id);
$stmt->execute();
$access_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($access_result['has_access'] == 0) {
    $_SESSION['error_message'] = "Δεν έχετε πρόσβαση σε αυτήν την κατηγορία!";
    header("Location: start.php");
    exit();
}

// Έλεγχος για τεστ ανά κεφάλαιο
if ($test_type == 'chapter' && $chapter_id <= 0) {
    $_SESSION['error_message'] = "Παρακαλώ επιλέξτε κεφάλαιο!";
    header("Location: start.php");
    exit();
}

// Επικύρωση αριθμού ερωτήσεων
if ($question_count < 5) $question_count = 5;
if ($question_count > 50) $question_count = 50;

// Λογική επιλογής ερωτήσεων ανάλογα με τον τύπο τεστ
$questions = [];

try {
    switch ($test_type) {
        case 'chapter':
            // Ερωτήσεις από συγκεκριμένο κεφάλαιο
            $query = "
                SELECT q.*, c.name AS chapter_name 
                FROM questions q
                JOIN test_chapters c ON q.chapter_id = c.id
                WHERE q.chapter_id = ? AND q.status = 'active'
                ORDER BY RAND()
                LIMIT ?
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", $chapter_id, $question_count);
            break;
            
        case 'random':
            // Τυχαίες ερωτήσεις από την κατηγορία
            $query = "
                SELECT q.*, c.name AS chapter_name 
                FROM questions q
                JOIN test_chapters c ON q.chapter_id = c.id
                JOIN test_subcategories s ON c.subcategory_id = s.id
                WHERE s.test_category_id = ? AND q.status = 'active'
                ORDER BY RAND()
                LIMIT ?
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", $category_id, $question_count);
            break;
            
        case 'simulation':
            // Προσομοίωση: κατανομή ερωτήσεων από όλα τα κεφάλαια
            $questions = getSimulationQuestions($mysqli, $category_id, $question_count);
            break;
            
        case 'difficult':
            // Εστίαση σε δύσκολες ή αναπάντητες ερωτήσεις
            $query = "
                SELECT q.*, c.name AS chapter_name 
                FROM questions q
                JOIN test_chapters c ON q.chapter_id = c.id
                JOIN test_subcategories s ON c.subcategory_id = s.id
                LEFT JOIN (
                    SELECT tra.question_id, COUNT(CASE WHEN tra.is_correct = 0 THEN 1 END) as wrong_count
                    FROM test_results_answers tra
                    JOIN test_results tr ON tra.test_result_id = tr.id
                    WHERE tr.user_id = ?
                    GROUP BY tra.question_id
                ) as user_answers ON q.id = user_answers.question_id
                WHERE s.test_category_id = ? 
                  AND q.status = 'active'
                ORDER BY COALESCE(user_answers.wrong_count, 0) DESC, RAND()
                LIMIT ?
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("iii", $user_id, $category_id, $question_count);
            break;
            
        default:
            throw new Exception("Μη έγκυρος τύπος τεστ!");
    }

    // Εκτέλεση του ερωτήματος αν δεν είναι προσομοίωση
    if ($test_type != 'simulation') {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Έλεγχος αν υπάρχουν αρκετές ερωτήσεις
    if (count($questions) == 0) {
        $_SESSION['error_message'] = "Δεν υπάρχουν διαθέσιμες ερωτήσεις για αυτό το τεστ!";
        header("Location: start.php");
        exit();
    }
    
    // Ανάκτηση απαντήσεων για κάθε ερώτηση
    foreach ($questions as &$question) {
        $answers_query = "SELECT * FROM test_answers WHERE question_id = ?";
        $answers_stmt = $mysqli->prepare($answers_query);
        $answers_stmt->bind_param("i", $question['id']);
        $answers_stmt->execute();
        $answers_result = $answers_stmt->get_result();
        
        $question['answers'] = [];
        while ($answer = $answers_result->fetch_assoc()) {
            $question['answers'][] = $answer;
        }
        $answers_stmt->close();
    }
    
    // Αποθήκευση στοιχείων τεστ στη συνεδρία
    $_SESSION['current_test'] = [
        'type' => $test_type,
        'category_id' => $category_id,
        'chapter_id' => $chapter_id,
        'questions' => $questions,
        'question_count' => count($questions),
        'time_limit' => $time_limit,
        'start_time' => time(),
        'user_answers' => []
    ];
    
    // Ανακατεύθυνση στη σελίδα του τεστ
    header("Location: test.php");
    exit();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Σφάλμα: " . $e->getMessage();
    header("Location: start.php");
    exit();
}

// Βοηθητική συνάρτηση για τεστ προσομοίωσης
function getSimulationQuestions($mysqli, $category_id, $total_count) {
    $questions = [];
    
    // Βρίσκουμε όλα τα κεφάλαια της κατηγορίας
    $query = "
        SELECT c.id, c.name
        FROM test_chapters c
        JOIN test_subcategories s ON c.subcategory_id = s.id
        WHERE s.test_category_id = ?
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $chapters = [];
    while ($row = $result->fetch_assoc()) {
        $chapters[] = $row;
    }
    $stmt->close();
    
    // Αν δεν υπάρχουν κεφάλαια
    if (empty($chapters)) {
        return [];
    }
    
    // Υπολογίζουμε πόσες ερωτήσεις θα πάρουμε από κάθε κεφάλαιο
    $chapters_count = count($chapters);
    $questions_per_chapter = floor($total_count / $chapters_count);
    $remainder = $total_count % $chapters_count;
    
    foreach ($chapters as $index => $chapter) {
        // Προσθέτουμε το remainder για να φτάσουμε το συνολικό αριθμό ερωτήσεων
        $chapter_question_count = $questions_per_chapter;
        if ($index < $remainder) {
            $chapter_question_count++;
        }
        
        if ($chapter_question_count <= 0) {
            continue;
        }
        
        $query = "
            SELECT q.*, ? AS chapter_name
            FROM questions q
            WHERE q.chapter_id = ? AND q.status = 'active'
            ORDER BY RAND()
            LIMIT ?
        ";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sii", $chapter['name'], $chapter['id'], $chapter_question_count);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Ανακατεύουμε τις ερωτήσεις για να μην είναι ομαδοποιημένες ανά κεφάλαιο
    shuffle($questions);
    
    return $questions;
}
?>