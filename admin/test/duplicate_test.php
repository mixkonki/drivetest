<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';

// Λήψη του ID του τεστ προς αντιγραφή
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($test_id === 0) {
    header("Location: quizzes.php?error=invalid_id");
    exit;
}

// Ανάκτηση των δεδομένων του αρχικού τεστ
$query = "SELECT * FROM test_generation WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $test_id);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();

if (!$test) {
    header("Location: quizzes.php?error=not_found");
    exit;
}

// Δημιουργία νέου ονόματος για το αντίγραφο
$new_name = $test['test_name'] . " (Αντίγραφο)";
$new_label = $test['label'] ? $test['label'] . " (Αντίγραφο)" : "";
$created_by = $_SESSION['user_id'];

// Εισαγωγή του νέου τεστ με βάση τα δεδομένα του αρχικού
$insert_query = "INSERT INTO test_generation (
                test_name, label, category_id, questions_count, time_limit,
                pass_percentage, selection_method, chapter_distribution, 
                display_answers_mode, is_practice, is_simulation, show_explanations,
                show_correct_answers, randomize_questions, randomize_answers,
                show_question_numbers, show_progress_bar, show_timer,
                max_attempts, required_user_role, primary_color, background_color,
                status, created_by, created_at) 
                SELECT ?, ?, category_id, questions_count, time_limit,
                pass_percentage, selection_method, chapter_distribution, 
                display_answers_mode, is_practice, is_simulation, show_explanations,
                show_correct_answers, randomize_questions, randomize_answers,
                show_question_numbers, show_progress_bar, show_timer,
                max_attempts, required_user_role, primary_color, background_color,
                'active', ?, NOW()
                FROM test_generation WHERE id = ?";

$stmt = $mysqli->prepare($insert_query);
$stmt->bind_param("ssii", $new_name, $new_label, $created_by, $test_id);

if (!$stmt->execute()) {
    header("Location: quizzes.php?error=duplicate_failed");
    exit;
}

$new_test_id = $stmt->insert_id;

// Αντιγραφή των ερωτήσεων του τεστ
$copy_questions_query = "INSERT INTO test_generation_questions (test_id, question_id, position)
                        SELECT ?, question_id, position
                        FROM test_generation_questions
                        WHERE test_id = ?";
$stmt = $mysqli->prepare($copy_questions_query);
$stmt->bind_param("ii", $new_test_id, $test_id);
$stmt->execute();

// Ανακατεύθυνση στη λίστα τεστ με μήνυμα επιτυχίας
header("Location: quizzes.php?success=duplicated");
exit;