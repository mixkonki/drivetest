<?php


require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Δημιουργία/Καταγραφή Log Αρχείου
function logMessage($message) {
    if (DEBUG) { // Χρήση της σταθεράς DEBUG από config
        $logFile = BASE_PATH . '/admin/test/debug_log.txt';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        if (!file_exists($logFile)) {
            file_put_contents($logFile, "=== LOG FILE CREATED ===\n");
        }
        file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] " . $message . "\n", FILE_APPEND);
    }
}

// ✅ Έλεγχος αν είναι POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("❌ [ERROR] Μη έγκυρη μέθοδος HTTP.");
    echo json_encode(["success" => false, "message" => "Μη έγκυρη μέθοδος HTTP."]);
    exit();
}

$action = $_POST['action'] ?? '';
logMessage("🔍 [INFO] Action: " . $action);

// ✅ Φόρτωση Υποκατηγοριών
if ($action === 'list_subcategories') {
    $query = "
        SELECT s.id, s.name, c.name AS category_name 
        FROM test_subcategories s
        JOIN test_categories c ON s.test_category_id = c.id
        ORDER BY s.name ASC";
    
    $result = $mysqli->query($query);

    if ($result) {
        $subcategories = $result->fetch_all(MYSQLI_ASSOC);
        logMessage("✅ [SUCCESS] Βρέθηκαν " . count($subcategories) . " υποκατηγορίες.");
        echo json_encode(["success" => true, "subcategories" => $subcategories]);
    } else {
        logMessage("❌ [ERROR] Σφάλμα SQL (Υποκατηγορίες): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα SQL (Υποκατηγορίες)."]);
    }
    exit();
}

// ✅ Φόρτωση Κεφαλαίων
if ($action === 'list_chapters') {
    $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
    logMessage("🔍 [INFO] Φόρτωση Κεφαλαίων για Υποκατηγορία ID: " . $subcategory_id);

    if ($subcategory_id === 0) {
        logMessage("❌ [ERROR] Μη έγκυρο Subcategory ID.");
        echo json_encode(["success" => false, "message" => "Μη έγκυρο Subcategory ID."]);
        exit();
    }

    $query = "SELECT id, name FROM test_chapters WHERE subcategory_id = ? ORDER BY name ASC";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα στην προετοιμασία SQL: " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα προετοιμασίας SQL."]);
        exit();
    }

    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $chapters = $result->fetch_all(MYSQLI_ASSOC);
        logMessage("✅ [SUCCESS] Βρέθηκαν " . count($chapters) . " κεφάλαια.");
        echo json_encode(["success" => true, "chapters" => $chapters]);
    } else {
        logMessage("❌ [ERROR] Σφάλμα SQL (Κεφάλαια): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα SQL (Κεφάλαια)."]);
    }
    $stmt->close();
    exit();
}

// ✅ Λίστα Ερωτήσεων
if ($action === 'list_questions') {
    $query = "SELECT q.id, q.question_text, q.question_type, q.created_at, 
                 c.name AS chapter_name, 
                 s.name AS subcategory_name, 
                 cat.name AS category_name, 
                 COUNT(a.id) AS answers_count, 
                 COALESCE(u.fullname, 'Άγνωστος') AS author, q.status, 
                 q.question_media, q.explanation_media  
          FROM questions q 
          LEFT JOIN test_answers a ON q.id = a.question_id  
          JOIN test_chapters c ON q.chapter_id = c.id 
          JOIN test_subcategories s ON c.subcategory_id = s.id 
          JOIN test_categories cat ON s.test_category_id = cat.id 
          LEFT JOIN users u ON q.author_id = u.id  
          GROUP BY q.id 
          ORDER BY q.created_at DESC";

    $result = $mysqli->query($query);

    if (!$result) {
        logMessage("❌ [ERROR] Σφάλμα SQL (Ερωτήσεις): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα SQL (Ερωτήσεις)."]);
        exit();
    }

    $questions = $result->fetch_all(MYSQLI_ASSOC);
    logMessage("✅ [SUCCESS] Βρέθηκαν " . count($questions) . " ερωτήσεις.");

    echo json_encode(["success" => true, "questions" => $questions]);
    exit();
}

// ✅ Αποθήκευση Ερώτησης
if ($action === 'save_question') {
    logMessage("🔍 [INFO] Ξεκίνησε αποθήκευση ερώτησης...");

    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $question_explanation = trim($_POST['explanation'] ?? '');
    $question_type = $_POST['question_type'] ?? 'single_choice';

    if (empty($question_text) || $chapter_id === 0) {
        logMessage("❌ [ERROR] Κάποια δεδομένα λείπουν!");
        echo json_encode(["success" => false, "message" => "Κάποια δεδομένα λείπουν!"]);
        exit();
    }

    $author_id = $_SESSION['user_id'] ?? 1;
    $uploadDir = BASE_PATH . '/admin/test/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $questionMediaPath = '';
    if (isset($_FILES['question_media']) && $_FILES['question_media']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['question_media']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            logMessage("❌ [ERROR] Μη επιτρεπτός τύπος αρχείου για ερώτηση: " . $fileType);
            echo json_encode(["success" => false, "message" => "Μη επιτρεπτός τύπος αρχείου για ερώτηση."]);
            exit();
        }
        if ($_FILES['question_media']['size'] > 10 * 1024 * 1024) { // 10MB
            logMessage("❌ [ERROR] Το αρχείο υπερβαίνει το μέγεθος 10MB για ερώτηση.");
            echo json_encode(["success" => false, "message" => "Το αρχείο υπερβαίνει το μέγεθος 10MB."]);
            exit();
        }
        $fileName = uniqid() . '_' . basename($_FILES['question_media']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['question_media']['tmp_name'], $targetPath)) {
            $questionMediaPath = $fileName;
            logMessage("✅ [SUCCESS] Αποθηκεύτηκε multimedia για ερώτηση: " . $fileName);
        } else {
            logMessage("❌ [ERROR] Σφάλμα αποθήκευσης multimedia ερώτησης: " . $_FILES['question_media']['error']);
        }
    }

    $explanationMediaPath = '';
    if (isset($_FILES['explanation_media']) && $_FILES['explanation_media']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['explanation_media']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            logMessage("❌ [ERROR] Μη επιτρεπτός τύπος αρχείου για επεξήγηση: " . $fileType);
            echo json_encode(["success" => false, "message" => "Μη επιτρεπτός τύπος αρχείου για επεξήγηση."]);
            exit();
        }
        if ($_FILES['explanation_media']['size'] > 10 * 1024 * 1024) { // 10MB
            logMessage("❌ [ERROR] Το αρχείο υπερβαίνει το μέγεθος 10MB για επεξήγηση.");
            echo json_encode(["success" => false, "message" => "Το αρχείο υπερβαίνει το μέγεθος 10MB."]);
            exit();
        }
        $fileName = uniqid() . '_' . basename($_FILES['explanation_media']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['explanation_media']['tmp_name'], $targetPath)) {
            $explanationMediaPath = $fileName;
            logMessage("✅ [SUCCESS] Αποθηκεύτηκε multimedia για επεξήγηση: " . $fileName);
        } else {
            logMessage("❌ [ERROR] Σφάλμα αποθήκευσης multimedia επεξήγησης: " . $_FILES['explanation_media']['error']);
        }
    }

    // Εισαγωγή ερώτησης στη βάση
    $query = "INSERT INTO questions (chapter_id, question_text, question_explanation, question_type, author_id, created_at, question_media, explanation_media) 
              VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("isssiss", $chapter_id, $question_text, $question_explanation, $question_type, $author_id, $questionMediaPath, $explanationMediaPath);

    if ($stmt->execute()) {
        $question_id = $stmt->insert_id;
        logMessage("✅ [SUCCESS] Ερώτηση αποθηκεύτηκε με ID: " . $question_id);

        // Διαχείριση απαντήσεων και multimedia
        $answers = json_decode($_POST['answers'] ?? '[]', true);
        $correct_answers = json_decode($_POST['correct_answers'] ?? '[]', true);
        $session_id = isset($_SESSION['session_id']) ? $_SESSION['session_id'] : 0; // Σύνδεση με session

        if (!empty($answers)) {
            foreach ($answers as $index => $answer) {
                $is_correct = in_array($answer, $correct_answers) ? 1 : 0;
                $answerMediaPath = '';

                if (isset($_FILES['answer_medias']) && isset($_FILES['answer_medias']['name'][$index]) && $_FILES['answer_medias']['error'][$index] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
                    $fileType = mime_content_type($_FILES['answer_medias']['tmp_name'][$index]);
                    if (!in_array($fileType, $allowedTypes)) {
                        logMessage("❌ [ERROR] Μη επιτρεπτός τύπος αρχείου για απάντηση: " . $fileType);
                        continue;
                    }
                    if ($_FILES['answer_medias']['size'][$index] > 10 * 1024 * 1024) { // 10MB
                        logMessage("❌ [ERROR] Το αρχείο υπερβαίνει το μέγεθος 10MB για απάντηση.");
                        continue;
                    }
                    $fileName = uniqid() . '_' . basename($_FILES['answer_medias']['name'][$index]);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['answer_medias']['tmp_name'][$index], $targetPath)) {
                        $answerMediaPath = $fileName;
                        logMessage("✅ [SUCCESS] Αποθηκεύτηκε multimedia για απάντηση: " . $fileName);
                    } else {
                        logMessage("❌ [ERROR] Σφάλμα αποθήκευσης multimedia απάντησης: " . $_FILES['answer_medias']['error'][$index]);
                    }
                }

                $query = "INSERT INTO test_answers (question_id, answer_text, is_correct, answer_media, session_id) VALUES (?, ?, ?, ?, ?)";
                $stmt_answer = $mysqli->prepare($query);
                $stmt_answer->bind_param("issii", $question_id, $answer, $is_correct, $answerMediaPath, $session_id);
                if (!$stmt_answer->execute()) {
                    logMessage("❌ [ERROR] Σφάλμα αποθήκευσης απάντησης: " . $stmt_answer->error);
                } else {
                    logMessage("✅ [SUCCESS] Αποθηκεύτηκε απάντηση: " . $answer);
                }
                $stmt_answer->close();
            }
        }

        echo json_encode(["success" => true, "message" => "Η ερώτηση αποθηκεύτηκε επιτυχώς!", "question_id" => $question_id]);
    } else {
        logMessage("❌ [ERROR] Σφάλμα κατά την αποθήκευση ερώτησης: " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα κατά την αποθήκευση ερώτησης."]);
    }
    $stmt->close();
    exit();
}

// ✅ Ανάκτηση Ερώτησης για Επεξεργασία
if ($action === 'get_question') {
    $question_id = intval($_POST['question_id'] ?? 0);
    logMessage("🔍 [INFO] Φόρτωση Ερώτησης ID: " . $question_id);

    if ($question_id === 0) {
        echo json_encode(["success" => false, "message" => "Μη έγκυρο Question ID."]);
        exit();
    }

    $query = "SELECT q.*, 
                     c.name AS chapter_name, 
                     s.name AS subcategory_name, 
                     cat.name AS category_name 
              FROM questions q 
              JOIN test_chapters c ON q.chapter_id = c.id 
              JOIN test_subcategories s ON c.subcategory_id = s.id 
              JOIN test_categories cat ON s.test_category_id = cat.id 
              WHERE q.id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Συλλογή απαντήσεων
        $query_answers = "SELECT answer_text, is_correct, answer_media FROM test_answers WHERE question_id = ? ORDER BY id ASC";
        $stmt_answers = $mysqli->prepare($query_answers);
        $stmt_answers->bind_param("i", $question_id);
        $stmt_answers->execute();
        $result_answers = $stmt_answers->get_result();
        $answers = $result_answers->fetch_all(MYSQLI_ASSOC);
        $row['answers'] = $answers;

        logMessage("✅ [SUCCESS] Βρέθηκαν " . count($answers) . " απαντήσεις για ερώτηση ID " . $question_id);
        logMessage("🔍 [DEBUG] Δεδομένα ερώτησης: " . json_encode($row, JSON_PRETTY_PRINT));
        logMessage("🔍 [INFO] Ερώτηση ανήκει σε: Κατηγορία: " . ($row['category_name'] ?: 'Κενό') . 
                   ", Υποκατηγορία: " . ($row['subcategory_name'] ?: 'Κενό') . 
                   ", Κεφάλαιο: " . ($row['chapter_name'] ?: 'Κενό'));
        echo json_encode(["success" => true, "question" => $row]);
    } else {
        logMessage("❌ [ERROR] Η ερώτηση με ID " . $question_id . " δεν βρέθηκε.");
        echo json_encode(["success" => false, "message" => "Η ερώτηση δεν βρέθηκε."]);
    }
    $stmt->close();
    exit();
}

// ✅ Καταγραφή Σφαλμάτων από Πελάτη
if ($action === 'log_client_error') {
    $message = $_POST['message'] ?? 'Άγνωστο σφάλμα';
    logMessage("❌ [CLIENT ERROR] " . $message);
    echo json_encode(["success" => true, "message" => "Σφάλμα καταγράφηκε."]);
    exit();
}

// ✅ Αν η ενέργεια δεν είναι γνωστή
logMessage("❌ [ERROR] Άγνωστη ενέργεια: " . $action);
echo json_encode(["success" => false, "message" => "Άγνωστη ενέργεια."]);
exit();
?>