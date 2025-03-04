<?php
// Διαδρομή: /test/submit_test.php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος αν υπάρχει τεστ σε εξέλιξη
if (!isset($_SESSION['current_test'])) {
    header("Location: start.php");
    exit();
}

$test = $_SESSION['current_test'];
$user_id = $_SESSION['user_id'];
$is_timeout = isset($_GET['timeout']) && $_GET['timeout'] == 1;

// Υπολογισμός χρόνου που πέρασε
$time_spent = time() - $test['start_time'];

// Υπολογισμός βαθμολογίας
// Επιβεβαίωση ότι το test['questions'] είναι πίνακας
if (!is_array($test['questions'])) {
    // Αν δεν είναι πίνακας, δημιούργησε έναν άδειο πίνακα ή ανακατεύθυνε
    $test['questions'] = []; 
}

// Επιβεβαίωση ότι το user_answers είναι πίνακας
if (!isset($test['user_answers']) || !is_array($test['user_answers'])) {
    $test['user_answers'] = [];
}

$total_questions = count($test['questions']);
$correct_answers = 0;
$unanswered_count = 0;
$results = [];

foreach ($test['questions'] as $question) {
    $question_id = $question['id'];
    
    // Έλεγχος αν υπάρχουν απαντήσεις για αυτή την ερώτηση
    if (!isset($test['user_answers'][$question_id]) || !is_array($test['user_answers'][$question_id])) {
        $user_answers = [];
    } else {
        $user_answers = $test['user_answers'][$question_id];
    }
    
    if (empty($user_answers)) {
        $unanswered_count++;
        $results[$question_id] = [
            'question' => $question,
            'user_answers' => [],
            'is_correct' => false,
            'status' => 'unanswered'
        ];
        continue;
    }
    
    // Έλεγχος αν το answers είναι πίνακας
    if (!isset($question['answers']) || !is_array($question['answers'])) {
        $question['answers'] = [];
    }
    
    // Ανάκτηση σωστών απαντήσεων
    $correct_answer_ids = [];
    foreach ($question['answers'] as $answer) {
        if ($answer['is_correct']) {
            $correct_answer_ids[] = $answer['id'];
        }
    }
    
    // Έλεγχος αν είναι σωστή η απάντηση
    $is_correct = false;
    
    if ($question['question_type'] === 'multiple_choice') {
        // Για ερωτήσεις πολλαπλών επιλογών, όλες οι σωστές πρέπει να επιλεγούν και καμία λάθος
        $is_correct = count(array_diff($correct_answer_ids, $user_answers)) === 0 
                   && count(array_diff($user_answers, $correct_answer_ids)) === 0;
    } else {
        // Για ερωτήσεις μονής επιλογής
        $is_correct = count($user_answers) === 1 && in_array($user_answers[0], $correct_answer_ids);
    }
    
    if ($is_correct) {
        $correct_answers++;
    }
    
    $results[$question_id] = [
        'question' => $question,
        'user_answers' => $user_answers,
        'correct_answers' => $correct_answer_ids,
        'is_correct' => $is_correct,
        'status' => $is_correct ? 'correct' : 'incorrect'
    ];
}

// Υπολογισμός βαθμολογίας
$score_percentage = ($total_questions > 0) ? ($correct_answers / $total_questions) * 100 : 0;
$score_decimal = round($score_percentage, 2); // για την αποθήκευση στη βάση ως decimal(5,2)
$success_threshold = 70; // 70% επιτυχία για να περάσει
$passed = $score_percentage >= $success_threshold;

// Αποθήκευση αποτελεσμάτων στη βάση
$test_result_id = null;

try {
    $mysqli->begin_transaction();
    
   // Εισαγωγή αποτελέσματος τεστ - προσαρμογή στις στήλες του πίνακα test_results
$query = "INSERT INTO test_results (
    user_id, test_type, test_category_id, chapter_id, 
    score, total_questions, time_spent, passed, 
    start_time, end_time
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())";

// Πρέπει να αποθηκεύσουμε τις τιμές σε μεταβλητές πριν το bind_param
$test_type = $test['type'];
$category_id = $test['category_id'];
$chapter_id = $test['chapter_id'];
$start_time = $test['start_time'];
$passed_value = $passed ? 1 : 0;

$stmt = $mysqli->prepare($query);
$stmt->bind_param("isiidiisi", 
$user_id, 
$test_type, 
$category_id, 
$chapter_id, 
$score_value, 
$total_questions, 
$time_spent, 
$passed_value, 
$start_time
);
    
    $stmt->execute();
    $test_result_id = $mysqli->insert_id;
    $stmt->close();
    
    // Εισαγωγή απαντήσεων
    foreach ($results as $question_id => $result) {
        $user_answer_ids = $result['user_answers'];
        $is_correct = $result['is_correct'] ? 1 : 0;
        
        if (empty($user_answer_ids)) {
            // Αν δεν απαντήθηκε, καταχωρούμε μόνο το question_id
            $query = "INSERT INTO test_results_answers (
                        test_result_id, question_id, user_answer_id, is_correct
                      ) VALUES (?, ?, NULL, 0)";
            
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", 
                $test_result_id, 
                $question_id
            );
            
            $stmt->execute();
            $stmt->close();
        } else {
            // Για κάθε απάντηση που έδωσε ο χρήστης
            foreach ($user_answer_ids as $answer_id) {
                $query = "INSERT INTO test_results_answers (
                            test_result_id, question_id, user_answer_id, is_correct
                          ) VALUES (?, ?, ?, ?)";
                
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("iiii", 
                    $test_result_id, 
                    $question_id, 
                    $answer_id, 
                    $is_correct
                );
                
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Σφάλμα στην υποβολή τεστ: " . $e->getMessage());
}

// Αποθήκευση αποτελεσμάτων στη συνεδρία για τη σελίδα αποτελεσμάτων
$_SESSION['test_result'] = [
    'test_result_id' => $test_result_id,
    'test' => $test,
    'results' => $results,
    'score' => $score_percentage,
    'correct_answers' => $correct_answers,
    'total_questions' => $total_questions,
    'time_spent' => $time_spent,
    'passed' => $passed,
    'unanswered_count' => $unanswered_count,
    'is_timeout' => $is_timeout
];

// Καθαρισμός τρέχοντος τεστ
unset($_SESSION['current_test']);

// Ανακατεύθυνση στη σελίδα αποτελεσμάτων
header("Location: results.php");
exit();
?>