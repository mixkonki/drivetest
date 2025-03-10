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

// Τροποποιήστε το τμήμα save_question στο question_actions.php

// ✅ Αποθήκευση Ερώτησης
if ($action === 'save_question') {
    logMessage("🔍 [INFO] Ξεκίνησε αποθήκευση ερώτησης...");

    // Βασικά δεδομένα
    $chapter_id = intval($_POST['chapter_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $question_explanation = trim($_POST['explanation'] ?? '');
    $question_type = $_POST['question_type'] ?? 'single_choice';
    
    // Επεξεργασία των απαντήσεων από το JSON
    $answers = json_decode($_POST['answers'] ?? '[]', true);
    $correct_answers = json_decode($_POST['correct_answers'] ?? '[]', true);
    
    logMessage("📊 [DEBUG] Ληφθέντα δεδομένα: chapter_id=$chapter_id, question_text=" . substr($question_text, 0, 30) . "..., answers=" . count($answers) . ", correct=" . count($correct_answers));

    // Έλεγχος για υποχρεωτικά πεδία
    $errors = [];
    if (empty($question_text)) {
        $errors[] = "Το κείμενο της ερώτησης είναι υποχρεωτικό";
        logMessage("❌ [ERROR] Το κείμενο της ερώτησης είναι κενό");
    }
    if ($chapter_id <= 0) {
        $errors[] = "Πρέπει να επιλέξετε κεφάλαιο";
        logMessage("❌ [ERROR] Δεν έχει επιλεχθεί κεφάλαιο (chapter_id=$chapter_id)");
    }
    if (empty($answers)) {
        $errors[] = "Πρέπει να προσθέσετε τουλάχιστον μία απάντηση";
        logMessage("❌ [ERROR] Δεν έχουν προστεθεί απαντήσεις");
    }
    if (empty($correct_answers)) {
        $errors[] = "Πρέπει να επιλέξετε τουλάχιστον μία σωστή απάντηση";
        logMessage("❌ [ERROR] Δεν έχουν επιλεχθεί σωστές απαντήσεις");
    }

    if (!empty($errors)) {
        $errorMessage = implode(". ", $errors);
        logMessage("❌ [ERROR] Αποτυχία αποθήκευσης: $errorMessage");
        echo json_encode(["success" => false, "message" => $errorMessage]);
        exit();
    }

    // Διαχείριση αρχείου για το πολυμέσο ερώτησης
    $uploadDir = BASE_PATH . '/admin/test/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $question_media = '';
    if (isset($_FILES['question_media']) && $_FILES['question_media']['error'] === UPLOAD_ERR_OK && $_FILES['question_media']['size'] > 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['question_media']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes) && $_FILES['question_media']['size'] <= 10 * 1024 * 1024) {
            $fileName = uniqid() . '_' . basename($_FILES['question_media']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['question_media']['tmp_name'], $targetPath)) {
                $question_media = $fileName;
                logMessage("✅ [SUCCESS] Αποθηκεύτηκε πολυμέσο ερώτησης: " . $fileName);
            } else {
                logMessage("⚠️ [WARNING] Σφάλμα αποθήκευσης πολυμέσου ερώτησης. Συνεχίζεται η αποθήκευση χωρίς πολυμέσο.");
            }
        } else {
            logMessage("⚠️ [WARNING] Μη αποδεκτός τύπος αρχείου ή μέγεθος για το πολυμέσο ερώτησης. Συνεχίζεται η αποθήκευση χωρίς πολυμέσο.");
        }
    }

    // Διαχείριση αρχείου για το πολυμέσο επεξήγησης
    $explanation_media = '';
    if (isset($_FILES['explanation_media']) && $_FILES['explanation_media']['error'] === UPLOAD_ERR_OK && $_FILES['explanation_media']['size'] > 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['explanation_media']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes) && $_FILES['explanation_media']['size'] <= 10 * 1024 * 1024) {
            $fileName = uniqid() . '_' . basename($_FILES['explanation_media']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['explanation_media']['tmp_name'], $targetPath)) {
                $explanation_media = $fileName;
                logMessage("✅ [SUCCESS] Αποθηκεύτηκε πολυμέσο επεξήγησης: " . $fileName);
            } else {
                logMessage("⚠️ [WARNING] Σφάλμα αποθήκευσης πολυμέσου επεξήγησης. Συνεχίζεται η αποθήκευση χωρίς πολυμέσο.");
            }
        } else {
            logMessage("⚠️ [WARNING] Μη αποδεκτός τύπος αρχείου ή μέγεθος για το πολυμέσο επεξήγησης. Συνεχίζεται η αποθήκευση χωρίς πολυμέσο.");
        }
    }

    // Ορισμός του συγγραφέα και της κατάστασης
    $author_id = $_SESSION['user_id'] ?? 1;
    $status = 'active';

    // Εισαγωγή της ερώτησης στη βάση
    $query = "INSERT INTO questions (chapter_id, question_text, question_explanation, question_type, author_id, created_at, status, question_media, explanation_media) 
              VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $mysqli->prepare($query);
    
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL: " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα βάσης δεδομένων: " . $mysqli->error]);
        exit();
    }
    
    $stmt->bind_param("isssisss", $chapter_id, $question_text, $question_explanation, $question_type, $author_id, $status, $question_media, $explanation_media);
    
    if (!$stmt->execute()) {
        logMessage("❌ [ERROR] Σφάλμα κατά την αποθήκευση ερώτησης: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα κατά την αποθήκευση ερώτησης: " . $stmt->error]);
        $stmt->close();
        exit();
    }
    
    $question_id = $stmt->insert_id;
    $stmt->close();
    
    logMessage("✅ [SUCCESS] Ερώτηση αποθηκεύτηκε με ID: " . $question_id);
    
    // Εισαγωγή των απαντήσεων
    if (!empty($answers)) {
        $success_count = 0;
        $insert_query = "INSERT INTO test_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
        
        foreach ($answers as $index => $answer_text) {
            if (empty(trim($answer_text))) continue; // Παράλειψη κενών απαντήσεων
            
            $stmt = $mysqli->prepare($insert_query);
            
            if (!$stmt) {
                logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (insert answer): " . $mysqli->error);
                continue;
            }
            
            $is_correct = in_array(strval($index), $correct_answers) ? 1 : 0;
            $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
            
            if ($stmt->execute()) {
                $success_count++;
                logMessage("✅ [SUCCESS] Απάντηση #" . ($index + 1) . " αποθηκεύτηκε επιτυχώς!");
            } else {
                logMessage("❌ [ERROR] Σφάλμα κατά την αποθήκευση απάντησης #" . ($index + 1) . ": " . $stmt->error);
            }
            
            $stmt->close();
        }
        
        logMessage("📊 [INFO] Συνολικά αποθηκεύτηκαν $success_count από " . count($answers) . " απαντήσεις.");
    }
    
    echo json_encode([
        "success" => true, 
        "message" => "Η ερώτηση αποθηκεύτηκε επιτυχώς!", 
        "question_id" => $question_id
    ]);
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

// ✅ Ενημέρωση Ερώτησης
if ($action === 'update_question') {
    logMessage("🔍 [INFO] Ξεκίνησε ενημέρωση ερώτησης...");

    $question_id = intval($_POST['id'] ?? 0);
    if ($question_id === 0) {
        logMessage("❌ [ERROR] Λείπει το ID της ερώτησης!");
        echo json_encode(["success" => false, "message" => "Λείπει το ID της ερώτησης!"]);
        exit();
    }

    // Ανάκτηση των υπαρχόντων δεδομένων για τη σύγκριση
    $query = "SELECT * FROM questions WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (get question): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα προετοιμασίας SQL."]);
        exit();
    }

    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_question = $result->fetch_assoc();
    $stmt->close();

    if (!$existing_question) {
        logMessage("❌ [ERROR] Η ερώτηση με ID $question_id δεν βρέθηκε.");
        echo json_encode(["success" => false, "message" => "Η ερώτηση δεν βρέθηκε."]);
        exit();
    }

    // Βασικά δεδομένα ερώτησης
    $chapter_id = intval($_POST['chapter_id'] ?? $existing_question['chapter_id']);
    $question_text = trim($_POST['question_text'] ?? $existing_question['question_text']);
    $question_explanation = trim($_POST['explanation'] ?? $existing_question['question_explanation']);
    $question_type = $_POST['question_type'] ?? $existing_question['question_type'];
    
    // Επεξεργασία των απαντήσεων από το JSON
    $answers = json_decode($_POST['answers'] ?? '[]', true);
    $correct_answers = json_decode($_POST['correct_answers'] ?? '[]', true);
    
    logMessage("📊 [DEBUG] Ληφθέντα δεδομένα: chapter_id=$chapter_id, answers=" . count($answers) . ", correct=" . count($correct_answers));

    // Έλεγχος για υποχρεωτικά πεδία
    if (empty($question_text)) {
        logMessage("❌ [ERROR] Το κείμενο της ερώτησης είναι υποχρεωτικό.");
        echo json_encode(["success" => false, "message" => "Το κείμενο της ερώτησης είναι υποχρεωτικό."]);
        exit();
    }

    // Διαχείριση αρχείων πολυμέσων (αν υπάρχουν)
    $uploadDir = BASE_PATH . '/admin/test/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Διατήρηση του υπάρχοντος question_media αν δεν ανεβάσουμε νέο
    $question_media = $existing_question['question_media']; 
    
    if (isset($_FILES['question_media']) && $_FILES['question_media']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['question_media']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes) && $_FILES['question_media']['size'] <= 10 * 1024 * 1024) {
            $fileName = uniqid() . '_' . basename($_FILES['question_media']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['question_media']['tmp_name'], $targetPath)) {
                $question_media = $fileName;
                logMessage("✅ [SUCCESS] Αποθηκεύτηκε νέο πολυμέσο ερώτησης: " . $fileName);
                
                // Διαγραφή του παλιού αρχείου αν υπάρχει
                if (!empty($existing_question['question_media']) && file_exists($uploadDir . $existing_question['question_media'])) {
                    unlink($uploadDir . $existing_question['question_media']);
                    logMessage("🗑️ [INFO] Διαγράφηκε το παλιό πολυμέσο ερώτησης: " . $existing_question['question_media']);
                }
            } else {
                logMessage("❌ [ERROR] Σφάλμα αποθήκευσης πολυμέσου ερώτησης.");
            }
        } else {
            logMessage("❌ [ERROR] Μη αποδεκτός τύπος αρχείου ή μέγεθος για το πολυμέσο ερώτησης.");
        }
    }

    // Διατήρηση του υπάρχοντος explanation_media αν δεν ανεβάσουμε νέο
    $explanation_media = $existing_question['explanation_media'];
    
    if (isset($_FILES['explanation_media']) && $_FILES['explanation_media']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
        $fileType = mime_content_type($_FILES['explanation_media']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes) && $_FILES['explanation_media']['size'] <= 10 * 1024 * 1024) {
            $fileName = uniqid() . '_' . basename($_FILES['explanation_media']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['explanation_media']['tmp_name'], $targetPath)) {
                $explanation_media = $fileName;
                logMessage("✅ [SUCCESS] Αποθηκεύτηκε νέο πολυμέσο επεξήγησης: " . $fileName);
                
                // Διαγραφή του παλιού αρχείου αν υπάρχει
                if (!empty($existing_question['explanation_media']) && file_exists($uploadDir . $existing_question['explanation_media'])) {
                    unlink($uploadDir . $existing_question['explanation_media']);
                    logMessage("🗑️ [INFO] Διαγράφηκε το παλιό πολυμέσο επεξήγησης: " . $existing_question['explanation_media']);
                }
            } else {
                logMessage("❌ [ERROR] Σφάλμα αποθήκευσης πολυμέσου επεξήγησης.");
            }
        } else {
            logMessage("❌ [ERROR] Μη αποδεκτός τύπος αρχείου ή μέγεθος για το πολυμέσο επεξήγησης.");
        }
    }

    // Ενημέρωση της ερώτησης
    $update_query = "UPDATE questions SET 
                    chapter_id = ?, 
                    question_text = ?, 
                    question_explanation = ?, 
                    question_type = ?, 
                    question_media = ?,
                    explanation_media = ? 
                    WHERE id = ?";
                    
    $stmt = $mysqli->prepare($update_query);
    
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (update): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα προετοιμασίας SQL."]);
        exit();
    }
    
    $stmt->bind_param("isssssi", $chapter_id, $question_text, $question_explanation, $question_type, $question_media, $explanation_media, $question_id);
    
    if (!$stmt->execute()) {
        logMessage("❌ [ERROR] Σφάλμα κατά την ενημέρωση ερώτησης: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα κατά την ενημέρωση ερώτησης."]);
        $stmt->close();
        exit();
    }
    
    $stmt->close();
    logMessage("✅ [SUCCESS] Ερώτηση ενημερώθηκε με επιτυχία!");
    
    // Διαγραφή των παλιών απαντήσεων
    $delete_query = "DELETE FROM test_answers WHERE question_id = ?";
    $stmt = $mysqli->prepare($delete_query);
    
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (delete answers): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα διαγραφής παλιών απαντήσεων."]);
        exit();
    }
    
    $stmt->bind_param("i", $question_id);
    
    if (!$stmt->execute()) {
        logMessage("❌ [ERROR] Σφάλμα κατά τη διαγραφή απαντήσεων: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα διαγραφής παλιών απαντήσεων."]);
        $stmt->close();
        exit();
    }
    
    $stmt->close();
    logMessage("✅ [SUCCESS] Παλιές απαντήσεις διαγράφηκαν με επιτυχία!");
    
    // Εισαγωγή των νέων απαντήσεων
    if (!empty($answers)) {
        $success_count = 0;
        $insert_query = "INSERT INTO test_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
        
        foreach ($answers as $index => $answer_text) {
            if (empty(trim($answer_text))) continue; // Παράλειψη κενών απαντήσεων
            
            $stmt = $mysqli->prepare($insert_query);
            
            if (!$stmt) {
                logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (insert answer): " . $mysqli->error);
                continue;
            }
            
            $is_correct = in_array(strval($index), $correct_answers) ? 1 : 0;
            $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
            
            if ($stmt->execute()) {
                $success_count++;
                logMessage("✅ [SUCCESS] Απάντηση #" . ($index + 1) . " αποθηκεύτηκε επιτυχώς!");
            } else {
                logMessage("❌ [ERROR] Σφάλμα κατά την αποθήκευση απάντησης #" . ($index + 1) . ": " . $stmt->error);
            }
            
            $stmt->close();
        }
        
        logMessage("📊 [INFO] Συνολικά αποθηκεύτηκαν $success_count από " . count($answers) . " απαντήσεις.");
    }
    
    echo json_encode([
        "success" => true, 
        "message" => "Η ερώτηση ενημερώθηκε επιτυχώς!"
    ]);
    exit();
}

// ✅ Διαγραφή Ερώτησης
if ($action === 'delete_question') {
    logMessage("🔍 [INFO] Ξεκίνησε διαγραφή ερώτησης...");

    $question_id = intval($_POST['id'] ?? 0);
    if ($question_id === 0) {
        logMessage("❌ [ERROR] Λείπει το ID της ερώτησης!");
        echo json_encode(["success" => false, "message" => "Λείπει το ID της ερώτησης!"]);
        exit();
    }

    // Ανάκτηση των υπαρχόντων δεδομένων της ερώτησης
    $query = "SELECT * FROM questions WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (get question): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα προετοιμασίας SQL."]);
        exit();
    }

    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $question = $result->fetch_assoc();
    $stmt->close();

    if (!$question) {
        logMessage("❌ [ERROR] Η ερώτηση με ID $question_id δεν βρέθηκε.");
        echo json_encode(["success" => false, "message" => "Η ερώτηση δεν βρέθηκε."]);
        exit();
    }

    // Έλεγχος αν η ερώτηση χρησιμοποιείται σε κάποιο τεστ
    $check_query = "SELECT COUNT(*) as count FROM test_generation_questions WHERE question_id = ?";
    $stmt = $mysqli->prepare($check_query);
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (check usage): " . $mysqli->error);
        echo json_encode(["success" => false, "message" => "Σφάλμα κατά τον έλεγχο χρήσης της ερώτησης."]);
        exit();
    }

    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $usage = $result->fetch_assoc();
    $stmt->close();

    if ($usage && $usage['count'] > 0) {
        logMessage("⚠️ [WARNING] Η ερώτηση χρησιμοποιείται σε " . $usage['count'] . " τεστ και δεν μπορεί να διαγραφεί.");
        echo json_encode([
            "success" => false, 
            "message" => "Η ερώτηση χρησιμοποιείται σε " . $usage['count'] . " τεστ και δεν μπορεί να διαγραφεί."
        ]);
        exit();
    }

    // Ξεκινάμε μια συναλλαγή για να εξασφαλίσουμε την ακεραιότητα των δεδομένων
    $mysqli->begin_transaction();

    try {
        // 1. Διαγραφή των απαντήσεων
        $delete_answers_query = "DELETE FROM test_answers WHERE question_id = ?";
        $stmt = $mysqli->prepare($delete_answers_query);
        if (!$stmt) {
            throw new Exception("Σφάλμα προετοιμασίας SQL (delete answers): " . $mysqli->error);
        }

        $stmt->bind_param("i", $question_id);
        if (!$stmt->execute()) {
            throw new Exception("Σφάλμα κατά τη διαγραφή απαντήσεων: " . $stmt->error);
        }
        
        $deleted_answers_count = $stmt->affected_rows;
        $stmt->close();
        logMessage("✅ [SUCCESS] Διαγράφηκαν $deleted_answers_count απαντήσεις.");

        // 2. Διαγραφή της ερώτησης
        $delete_question_query = "DELETE FROM questions WHERE id = ?";
        $stmt = $mysqli->prepare($delete_question_query);
        if (!$stmt) {
            throw new Exception("Σφάλμα προετοιμασίας SQL (delete question): " . $mysqli->error);
        }

        $stmt->bind_param("i", $question_id);
        if (!$stmt->execute()) {
            throw new Exception("Σφάλμα κατά τη διαγραφή ερώτησης: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Η ερώτηση δεν διαγράφηκε. Ίσως έχει ήδη διαγραφεί.");
        }
        
        $stmt->close();
        logMessage("✅ [SUCCESS] Η ερώτηση διαγράφηκε επιτυχώς.");

        // 3. Διαγραφή των αρχείων πολυμέσων αν υπάρχουν
        $uploadDir = BASE_PATH . '/admin/test/uploads/';

        if (!empty($question['question_media']) && file_exists($uploadDir . $question['question_media'])) {
            if (unlink($uploadDir . $question['question_media'])) {
                logMessage("✅ [SUCCESS] Διαγράφηκε το αρχείο πολυμέσου ερώτησης: " . $question['question_media']);
            } else {
                logMessage("⚠️ [WARNING] Αδυναμία διαγραφής αρχείου πολυμέσου ερώτησης: " . $question['question_media']);
            }
        }

        if (!empty($question['explanation_media']) && file_exists($uploadDir . $question['explanation_media'])) {
            if (unlink($uploadDir . $question['explanation_media'])) {
                logMessage("✅ [SUCCESS] Διαγράφηκε το αρχείο πολυμέσου επεξήγησης: " . $question['explanation_media']);
            } else {
                logMessage("⚠️ [WARNING] Αδυναμία διαγραφής αρχείου πολυμέσου επεξήγησης: " . $question['explanation_media']);
            }
        }

        // Επιβεβαίωση της συναλλαγής
        $mysqli->commit();
        logMessage("✅ [SUCCESS] Η συναλλαγή ολοκληρώθηκε επιτυχώς!");
        
        echo json_encode([
            "success" => true, 
            "message" => "Η ερώτηση διαγράφηκε επιτυχώς!"
        ]);
        
    } catch (Exception $e) {
        // Αναίρεση της συναλλαγής σε περίπτωση σφάλματος
        $mysqli->rollback();
        logMessage("❌ [ERROR] Αναίρεση συναλλαγής λόγω σφάλματος: " . $e->getMessage());
        
        echo json_encode([
            "success" => false, 
            "message" => "Σφάλμα κατά τη διαγραφή ερώτησης: " . $e->getMessage()
        ]);
    }
    
    exit();
}

// ✅ Αν η ενέργεια δεν είναι γνωστή
logMessage("❌ [ERROR] Άγνωστη ενέργεια: " . $action);

// Προσθέστε τον παρακάτω κώδικα στο question_actions.php, πριν την τελευταία γραμμή
// που λέει "echo json_encode(["success" => false, "message" => "Άγνωστη ενέργεια."]);"

// ✅ Μαζική Διαγραφή Ερωτήσεων
if ($action === 'bulk_delete') {
    logMessage("🔍 [INFO] Ξεκίνησε μαζική διαγραφή ερωτήσεων...");

    // Λήψη των IDs των ερωτήσεων από το POST
    $question_ids_json = $_POST['question_ids'] ?? '[]';
    $question_ids = json_decode($question_ids_json, true);
    
    if (empty($question_ids) || !is_array($question_ids)) {
        logMessage("❌ [ERROR] Δεν δόθηκαν έγκυρα IDs ερωτήσεων για διαγραφή");
        echo json_encode([
            "success" => false, 
            "message" => "Δεν επιλέχθηκαν ερωτήσεις για διαγραφή."
        ]);
        exit();
    }
    
    logMessage("📊 [INFO] Ζητήθηκε διαγραφή " . count($question_ids) . " ερωτήσεων: " . implode(', ', $question_ids));
    
    // Έλεγχος αν κάποια από τις ερωτήσεις χρησιμοποιείται σε κάποιο τεστ
    $used_question_ids = [];
    $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
    
    $check_query = "SELECT DISTINCT question_id FROM test_generation_questions WHERE question_id IN ($placeholders)";
    $stmt = $mysqli->prepare($check_query);
    
    if (!$stmt) {
        logMessage("❌ [ERROR] Σφάλμα προετοιμασίας SQL (check usage): " . $mysqli->error);
        echo json_encode([
            "success" => false, 
            "message" => "Σφάλμα κατά τον έλεγχο χρήσης των ερωτήσεων."
        ]);
        exit();
    }
    
    // Δημιουργία της παραμέτρου τύπων για το bind_param
    $types = str_repeat('i', count($question_ids));
    // Χρήση του ... operator για να περάσουμε τα question_ids ως μεμονωμένες παραμέτρους
    $stmt->bind_param($types, ...$question_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $used_question_ids[] = $row['question_id'];
    }
    $stmt->close();
    
    // Αν βρέθηκαν ερωτήσεις που χρησιμοποιούνται, τις αφαιρούμε από τις προς διαγραφή
    if (!empty($used_question_ids)) {
        logMessage("⚠️ [WARNING] Βρέθηκαν " . count($used_question_ids) . " ερωτήσεις που χρησιμοποιούνται σε τεστ");
        $question_ids = array_diff($question_ids, $used_question_ids);
    }
    
    if (empty($question_ids)) {
        logMessage("❌ [ERROR] Όλες οι επιλεγμένες ερωτήσεις χρησιμοποιούνται σε τεστ και δεν μπορούν να διαγραφούν");
        echo json_encode([
            "success" => false, 
            "message" => "Όλες οι επιλεγμένες ερωτήσεις χρησιμοποιούνται σε τεστ και δεν μπορούν να διαγραφούν."
        ]);
        exit();
    }
    
    // Ξεκινάμε μια συναλλαγή για να εξασφαλίσουμε την ακεραιότητα των δεδομένων
    $mysqli->begin_transaction();
    
    try {
        // Ανάκτηση των στοιχείων των ερωτήσεων για τα πολυμέσα
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $query = "SELECT id, question_media, explanation_media FROM questions WHERE id IN ($placeholders)";
        $stmt = $mysqli->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Σφάλμα προετοιμασίας SQL (get questions): " . $mysqli->error);
        }
        
        $types = str_repeat('i', count($question_ids));
        $stmt->bind_param($types, ...$question_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $questions_to_delete = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // 1. Διαγραφή των απαντήσεων
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $delete_answers_query = "DELETE FROM test_answers WHERE question_id IN ($placeholders)";
        $stmt = $mysqli->prepare($delete_answers_query);
        
        if (!$stmt) {
            throw new Exception("Σφάλμα προετοιμασίας SQL (delete answers): " . $mysqli->error);
        }
        
        $types = str_repeat('i', count($question_ids));
        $stmt->bind_param($types, ...$question_ids);
        
        if (!$stmt->execute()) {
            throw new Exception("Σφάλμα κατά τη διαγραφή απαντήσεων: " . $stmt->error);
        }
        
        $deleted_answers_count = $stmt->affected_rows;
        $stmt->close();
        logMessage("✅ [SUCCESS] Διαγράφηκαν $deleted_answers_count απαντήσεις.");
        
        // 2. Διαγραφή των ερωτήσεων
        $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
        $delete_questions_query = "DELETE FROM questions WHERE id IN ($placeholders)";
        $stmt = $mysqli->prepare($delete_questions_query);
        
        if (!$stmt) {
            throw new Exception("Σφάλμα προετοιμασίας SQL (delete questions): " . $mysqli->error);
        }
        
        $types = str_repeat('i', count($question_ids));
        $stmt->bind_param($types, ...$question_ids);
        
        if (!$stmt->execute()) {
            throw new Exception("Σφάλμα κατά τη διαγραφή ερωτήσεων: " . $stmt->error);
        }
        
        $deleted_questions_count = $stmt->affected_rows;
        $stmt->close();
        logMessage("✅ [SUCCESS] Διαγράφηκαν $deleted_questions_count ερωτήσεις.");
        
        // 3. Διαγραφή των αρχείων πολυμέσων
        $uploadDir = BASE_PATH . '/admin/test/uploads/';
        $deleted_files = 0;
        
        foreach ($questions_to_delete as $question) {
            if (!empty($question['question_media']) && file_exists($uploadDir . $question['question_media'])) {
                if (unlink($uploadDir . $question['question_media'])) {
                    $deleted_files++;
                    logMessage("✅ [SUCCESS] Διαγράφηκε το αρχείο πολυμέσου ερώτησης: " . $question['question_media']);
                } else {
                    logMessage("⚠️ [WARNING] Αδυναμία διαγραφής αρχείου πολυμέσου ερώτησης: " . $question['question_media']);
                }
            }
            
            if (!empty($question['explanation_media']) && file_exists($uploadDir . $question['explanation_media'])) {
                if (unlink($uploadDir . $question['explanation_media'])) {
                    $deleted_files++;
                    logMessage("✅ [SUCCESS] Διαγράφηκε το αρχείο πολυμέσου επεξήγησης: " . $question['explanation_media']);
                } else {
                    logMessage("⚠️ [WARNING] Αδυναμία διαγραφής αρχείου πολυμέσου επεξήγησης: " . $question['explanation_media']);
                }
            }
        }
        
        // Επιβεβαίωση της συναλλαγής
        $mysqli->commit();
        
        $message = "Διαγράφηκαν επιτυχώς $deleted_questions_count ερωτήσεις και $deleted_answers_count απαντήσεις";
        
        if (!empty($used_question_ids)) {
            $message .= ". " . count($used_question_ids) . " ερωτήσεις εξαιρέθηκαν γιατί χρησιμοποιούνται σε τεστ.";
        }
        
        logMessage("✅ [SUCCESS] " . $message);
        
        echo json_encode([
            "success" => true, 
            "message" => $message,
            "deleted_count" => $deleted_questions_count,
            "skipped_ids" => $used_question_ids
        ]);
        
    } catch (Exception $e) {
        // Αναίρεση της συναλλαγής σε περίπτωση σφάλματος
        $mysqli->rollback();
        logMessage("❌ [ERROR] Αναίρεση συναλλαγής λόγω σφάλματος: " . $e->getMessage());
        
        echo json_encode([
            "success" => false, 
            "message" => "Σφάλμα κατά τη μαζική διαγραφή ερωτήσεων: " . $e->getMessage()
        ]);
    }
    
    exit();
}
echo json_encode(["success" => false, "message" => "Άγνωστη ενέργεια."]);
exit();
?>