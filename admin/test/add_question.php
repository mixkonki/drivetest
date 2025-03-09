<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Βεβαιωνόμαστε ότι η κωδικοποίηση είναι UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Λειτουργία για καθαρισμό κωδικοποίησης
function clean_encoding($string) {
    if (empty($string)) {
        return '';
    }
    // Αφαίρεση BOM αν υπάρχει
    $string = str_replace("\xEF\xBB\xBF", '', $string);
    
    // Logging για debugging της κωδικοποίησης
    log_debug("Processing string (raw): " . bin2hex($string) . " (length: " . strlen($string) . ")");

    // Δοκιμάζουμε τις κωδικοποιήσεις, δίνοντας προτεραιότητα στη Windows-1253
    $encodings_to_try = ['Windows-1253', 'UTF-8', 'ISO-8859-7', 'ASCII'];
    foreach ($encodings_to_try as $encoding) {
        if ($encoding === 'Windows-1253') {
            // Χρησιμοποιούμε iconv() για Windows-1253
            try {
                $converted = iconv('Windows-1253', 'UTF-8//IGNORE', $string);
                if ($converted !== false) {
                    $cleaned = trim(preg_replace('/\s+/', ' ', $converted));
                    log_debug("Converted from Windows-1253 to UTF-8: $cleaned");
                    return $cleaned;
                }
            } catch (Exception $e) {
                log_debug("Failed to convert from Windows-1253: " . $e->getMessage());
                continue;
            }
        } else {
            // Δοκιμάζουμε με mb_detect_encoding()
            $detected = mb_detect_encoding($string, $encoding, true);
            if ($detected !== false) {
                $cleaned = trim(preg_replace('/\s+/', ' ', $string));
                log_debug("Detected encoding $encoding, cleaned: $cleaned");
                return $cleaned;
            }
        }
    }
    
    // Αν όλες αποτυγχάνουν, επιστρέφουμε το string ως UTF-8 με ignore
    try {
        $fallback = iconv('Windows-1253', 'UTF-8//IGNORE', $string);
        $cleaned = trim(preg_replace('/\s+/', ' ', $fallback));
        log_debug("Fallback to Windows-1253 to UTF-8: $cleaned");
        return $cleaned;
    } catch (Exception $e) {
        log_debug("Failed fallback conversion: " . $e->getMessage());
        return trim(preg_replace('/\s+/', ' ', $string)); // Επιστρέφουμε το ακατέργαστο string
    }
}

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Ανάκτηση όλων των κατηγοριών, υποκατηγοριών και κεφαλαίων
$categories = $mysqli->query("SELECT id, name FROM test_categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$subcategories = $mysqli->query("SELECT id, name, test_category_id FROM test_subcategories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$chapters = $mysqli->query("SELECT id, name, subcategory_id FROM test_chapters ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Διευρυμένοι τύποι ερωτήσεων
$question_types = [
    'single_choice' => 'Πολλαπλής Επιλογής (1 σωστή)',
    'multiple_choice' => 'Πολλαπλών Σωστών',
    'true_false' => 'Σωστό/Λάθος',
    'fill_in_blank' => 'Συμπλήρωση Κενών',
    'matching' => 'Αντιστοίχισης',
    'ordering' => 'Ταξινόμησης',
    'short_answer' => 'Σύντομης Απάντησης',
    'essay' => 'Ανάπτυξης'
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'single') {
        // Μονή εισαγωγή ερώτησης
        $category_id = intval($_POST['category_id']);
        $subcategory_id = intval($_POST['subcategory_id']);
        $chapter_id = intval($_POST['chapter_id']);
        $question_text = trim($_POST['question_text']);
        $explanation = trim($_POST['explanation'] ?? '');
        $question_type = $_POST['question_type'] ?? 'single_choice';
        $difficulty_level = intval($_POST['difficulty_level'] ?? 1);
        $tags = trim($_POST['tags'] ?? '');
        
        // Διαφορετικός χειρισμός απαντήσεων ανάλογα με τον τύπο ερώτησης
        $answers = [];
        $correct_answers = [];
        
        switch ($question_type) {
            case 'single_choice':
            case 'multiple_choice':
                $answers = $_POST['answers'] ?? [];
                $correct_answers = $_POST['correct_answers'] ?? [];
                break;
                
            case 'true_false':
                $answers = ['Σωστό', 'Λάθος'];
                $correct_answers = [$_POST['true_false_answer']];
                break;
                
            case 'fill_in_blank':
                $answers = $_POST['blank_answers'] ?? [];
                $correct_answers = array_keys($answers); // Όλες οι απαντήσεις είναι σωστές
                break;
                
            case 'matching':
                $left_items = $_POST['matching_left'] ?? [];
                $right_items = $_POST['matching_right'] ?? [];
                
                // Δημιουργία των ζευγών αντιστοίχισης
                for ($i = 0; $i < count($left_items); $i++) {
                    if (!empty($left_items[$i]) && !empty($right_items[$i])) {
                        $answers[] = $left_items[$i] . " => " . $right_items[$i];
                        $correct_answers[] = $i; // Όλα τα ζεύγη είναι σωστά
                    }
                }
                break;
                
            case 'ordering':
                $ordering_items = $_POST['ordering_items'] ?? [];
                $answers = $ordering_items;
                $correct_answers = array_keys($ordering_items); // Η σειρά είναι η σωστή απάντηση
                break;
                
            case 'short_answer':
            case 'essay':
                $answers = [$_POST['model_answer'] ?? ''];
                $correct_answers = [0]; // Το μοντέλο απάντησης είναι η σωστή απάντηση
                break;
        }

        $uploadDir = BASE_PATH . '/admin/test/uploads/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        $question_media = '';
        if (isset($_FILES['question_media']) && $_FILES['question_media']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'video/mp4', 'audio/mpeg'];
            $fileType = mime_content_type($_FILES['question_media']['tmp_name']);
            if (in_array($fileType, $allowedTypes) && $_FILES['question_media']['size'] <= 10 * 1024 * 1024) {
                $fileName = uniqid() . '_' . basename($_FILES['question_media']['name']);
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['question_media']['tmp_name'], $targetPath)) {
                    $question_media = $fileName;
                }
            }
        }
        
        // Έλεγχος αν το κεφάλαιο δεν υπάρχει αλλά έχουμε υποκατηγορία και όνομα κεφαλαίου
        if ($chapter_id <= 0 && $subcategory_id > 0 && !empty($_POST['new_chapter_name'])) {
            $new_chapter_name = trim($_POST['new_chapter_name']);
            
            // Δημιουργία νέου κεφαλαίου
            $stmt = $mysqli->prepare("INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)");
            $stmt->bind_param("si", $new_chapter_name, $subcategory_id);
            if ($stmt->execute()) {
                $chapter_id = $stmt->insert_id;
                log_debug("Created new chapter: '$new_chapter_name' with ID: $chapter_id");
            } else {
                $error = "❌ Σφάλμα κατά τη δημιουργία κεφαλαίου: " . $stmt->error;
                log_debug($error);
            }
            $stmt->close();
        }
        
        // Έλεγχος αν η υποκατηγορία δεν υπάρχει αλλά έχουμε κατηγορία και όνομα υποκατηγορίας
        if ($subcategory_id <= 0 && $category_id > 0 && !empty($_POST['new_subcategory_name'])) {
            $new_subcategory_name = trim($_POST['new_subcategory_name']);
            
            // Δημιουργία νέας υποκατηγορίας
            $stmt = $mysqli->prepare("INSERT INTO test_subcategories (name, test_category_id) VALUES (?, ?)");
            $stmt->bind_param("si", $new_subcategory_name, $category_id);
            if ($stmt->execute()) {
                $subcategory_id = $stmt->insert_id;
                log_debug("Created new subcategory: '$new_subcategory_name' with ID: $subcategory_id");
                
                // Αν έχουμε και νέο κεφάλαιο, το δημιουργούμε
                if (!empty($_POST['new_chapter_name'])) {
                    $new_chapter_name = trim($_POST['new_chapter_name']);
                    $stmt = $mysqli->prepare("INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $new_chapter_name, $subcategory_id);
                    if ($stmt->execute()) {
                        $chapter_id = $stmt->insert_id;
                        log_debug("Created new chapter: '$new_chapter_name' with ID: $chapter_id");
                    }
                    $stmt->close();
                }
            } else {
                $error = "❌ Σφάλμα κατά τη δημιουργία υποκατηγορίας: " . $stmt->error;
                log_debug($error);
            }
            $stmt->close();
        }
        
        // Έλεγχος αν η κατηγορία δεν υπάρχει αλλά έχουμε όνομα κατηγορίας
        if ($category_id <= 0 && !empty($_POST['new_category_name'])) {
            $new_category_name = trim($_POST['new_category_name']);
            
            // Δημιουργία νέας κατηγορίας
            $stmt = $mysqli->prepare("INSERT INTO test_categories (name) VALUES (?)");
            $stmt->bind_param("s", $new_category_name);
            if ($stmt->execute()) {
                $category_id = $stmt->insert_id;
                log_debug("Created new category: '$new_category_name' with ID: $category_id");
                
                // Αν έχουμε και νέα υποκατηγορία, τη δημιουργούμε
                if (!empty($_POST['new_subcategory_name'])) {
                    $new_subcategory_name = trim($_POST['new_subcategory_name']);
                    $stmt = $mysqli->prepare("INSERT INTO test_subcategories (name, test_category_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $new_subcategory_name, $category_id);
                    if ($stmt->execute()) {
                        $subcategory_id = $stmt->insert_id;
                        log_debug("Created new subcategory: '$new_subcategory_name' with ID: $subcategory_id");
                        
                        // Αν έχουμε και νέο κεφάλαιο, το δημιουργούμε
                        if (!empty($_POST['new_chapter_name'])) {
                            $new_chapter_name = trim($_POST['new_chapter_name']);
                            $stmt = $mysqli->prepare("INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)");
                            $stmt->bind_param("si", $new_chapter_name, $subcategory_id);
                            if ($stmt->execute()) {
                                $chapter_id = $stmt->insert_id;
                                log_debug("Created new chapter: '$new_chapter_name' with ID: $chapter_id");
                            }
                            $stmt->close();
                        }
                    }
                    $stmt->close();
                }
            } else {
                $error = "❌ Σφάλμα κατά τη δημιουργία κατηγορίας: " . $stmt->error;
                log_debug($error);
            }
            $stmt->close();
        }

        if (!empty($question_text) && $chapter_id > 0) {
            $author_id = $_SESSION['user_id'] ?? 1;
            $status = 'active';
            
            // Μετατροπή των tags σε JSON array
            $tags_array = !empty($tags) ? explode(',', $tags) : [];
            $tags_json = json_encode(array_map('trim', $tags_array));
            
            // Αποθήκευση ερώτησης με επιπλέον πεδία
            $query = "INSERT INTO questions (chapter_id, question_text, question_explanation, question_type, author_id, question_media, status, difficulty_level, tags) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("isssissis", $chapter_id, $question_text, $explanation, $question_type, $author_id, $question_media, $status, $difficulty_level, $tags_json);
            
            if ($stmt->execute()) {
                $question_id = $stmt->insert_id;
                
                // Αποθήκευση απαντήσεων
                foreach ($answers as $index => $answer_text) {
                    if (!empty($answer_text)) {
                        $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                        $query = "INSERT INTO test_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                        $stmt_answer = $mysqli->prepare($query);
                        $stmt_answer->bind_param("isi", $question_id, $answer_text, $is_correct);
                        $stmt_answer->execute();
                        $stmt_answer->close();
                    }
                }
                
                $success = "✅ Η ερώτηση αποθηκεύτηκε επιτυχώς!";
                // Ανακατεύθυνση μόνο αν δεν έχουμε ζητήσει να παραμείνουμε στη σελίδα
                if (!isset($_POST['stay_on_page'])) {
                    header("Location: manage_questions.php?success=added");
                    exit();
                }
            } else {
                $error = "❌ Σφάλμα: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "❌ Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.";
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] === 'bulk') {
        // Κώδικας για μαζική εισαγωγή - θα υλοποιηθεί ξεχωριστά
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προσθήκη Ερώτησης</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/test_styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/enhanced_question.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
<main class="admin-container">
    <h2>➕ Προσθήκη Ερώτησης</h2>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-error">
        <p><?= $error ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        <p><?= $success ?></p>
    </div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab-btn active" data-tab="single-entry">Μονή Εισαγωγή</button>
        <button class="tab-btn" data-tab="bulk-import">Μαζική Εισαγωγή</button>
    </div>
    
    <div class="tab-content active" id="single-entry-tab">
        <form method="POST" enctype="multipart/form-data" id="single-question-form">
            <input type="hidden" name="form_type" value="single">
            
            <!-- Τμήμα κατηγορίας/υποκατηγορίας/κεφαλαίου -->
            <div class="form-section">
                <h3>Κατηγοριοποίηση Ερώτησης</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Κατηγορία Τεστ:</label>
                        <div class="input-with-action">
                            <select name="category_id" id="category_id">
                                <option value="">-- Επιλέξτε Κατηγορία --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="new">+ Νέα Κατηγορία</option>
                            </select>
                            <button type="button" class="btn-add" id="add-category-btn">+</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subcategory_id">Υποκατηγορία:</label>
                        <div class="input-with-action">
                            <select name="subcategory_id" id="subcategory_id" disabled>
                                <option value="">-- Επιλέξτε πρώτα Κατηγορία --</option>
                            </select>
                            <button type="button" class="btn-add" id="add-subcategory-btn" disabled>+</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="chapter_id">Κεφάλαιο:</label>
                        <div class="input-with-action">
                            <select name="chapter_id" id="chapter_id" disabled>
                                <option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>
                            </select>
                            <button type="button" class="btn-add" id="add-chapter-btn" disabled>+</button>
                        </div>
                    </div>
                </div>
                
                <!-- Πεδία για νέα κατηγορία/υποκατηγορία/κεφάλαιο (αρχικά κρυμμένα) -->
                <div id="new-category-fields" class="hidden">
                    <div class="form-group">
                        <label for="new_category_name">Όνομα Νέας Κατηγορίας:</label>
                        <input type="text" name="new_category_name" id="new_category_name" placeholder="Εισάγετε όνομα κατηγορίας">
                    </div>
                </div>
                
                <div id="new-subcategory-fields" class="hidden">
                    <div class="form-group">
                        <label for="new_subcategory_name">Όνομα Νέας Υποκατηγορίας:</label>
                        <input type="text" name="new_subcategory_name" id="new_subcategory_name" placeholder="Εισάγετε όνομα υποκατηγορίας">
                    </div>
                </div>
                
                <div id="new-chapter-fields" class="hidden">
                    <div class="form-group">
                        <label for="new_chapter_name">Όνομα Νέου Κεφαλαίου:</label>
                        <input type="text" name="new_chapter_name" id="new_chapter_name" placeholder="Εισάγετε όνομα κεφαλαίου">
                    </div>
                </div>
            </div>
            
            <!-- Βασικές πληροφορίες ερώτησης -->
            <div class="form-section">
                <h3>Βασικές Πληροφορίες</h3>
                
                <div class="form-group">
                    <label for="question_text">Κείμενο Ερώτησης:</label>
                    <textarea name="question_text" id="question_text" rows="4" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="question_type">Τύπος Ερώτησης:</label>
                        <select name="question_type" id="question_type">
                            <?php foreach ($question_types as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty_level">Επίπεδο Δυσκολίας:</label>
                        <select name="difficulty_level" id="difficulty_level">
                            <option value="1">Εύκολο</option>
                            <option value="2">Μέτριο</option>
                            <option value="3">Δύσκολο</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Ετικέτες:</label>
                        <input type="text" name="tags" id="tags" placeholder="Διαχωρισμός με κόμμα, π.χ.: σήματα, κανόνες">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="question_media">Πολυμέσα Ερώτησης:</label>
                    <input type="file" name="question_media" id="question_media" accept="image/*,video/*,audio/*">
                    <span class="help-text">Υποστηριζόμενα αρχεία: εικόνες, βίντεο, ήχος (μέγιστο μέγεθος: 10MB)</span>
                </div>
            </div>
            
            <!-- Απαντήσεις (δυναμικό τμήμα ανάλογα με τον τύπο ερώτησης) -->
            <div class="form-section">
                <h3>Απαντήσεις</h3>
                
                <!-- Πολλαπλής επιλογής (μία σωστή) -->
                <div class="question-answers" id="single_choice_answers">
                    <p class="help-text">Προσθέστε απαντήσεις και επιλέξτε τη σωστή.</p>
                    <div id="single_choice_container" class="answers-container">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="answer-entry">
                            <input type="text" name="answers[]" placeholder="Απάντηση <?= $i + 1 ?>" class="answer-text">
                            <input type="checkbox" name="correct_answers[]" value="<?= $i ?>" class="answer-correct" <?= ($i == 0) ? 'checked' : '' ?>>
                            <label>Σωστή</label>
                            <button type="button" class="remove-answer">❌</button>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" class="add-answer-btn" data-target="single_choice_container">+ Προσθήκη Απάντησης</button>
                </div>
                
                <!-- Πολλαπλών επιλογών (πολλαπλές σωστές) -->
                <div class="question-answers" id="multiple_choice_answers" style="display: none;">
                    <p class="help-text">Προσθέστε απαντήσεις και επιλέξτε τις σωστές (μία ή περισσότερες).</p>
                    <div id="multiple_choice_container" class="answers-container">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="answer-entry">
                            <input type="text" name="answers[]" placeholder="Απάντηση <?= $i + 1 ?>" class="answer-text">
                            <input type="checkbox" name="correct_answers[]" value="<?= $i ?>" class="answer-correct">
                            <label>Σωστή</label>
                            <button type="button" class="remove-answer">❌</button>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" class="add-answer-btn" data-target="multiple_choice_container">+ Προσθήκη Απάντησης</button>
                </div>
                
                <!-- Σωστό/Λάθος -->
                <div class="question-answers" id="true_false_answers" style="display: none;">
                    <p class="help-text">Επιλέξτε τη σωστή απάντηση.</p>
                    <div class="answers-container">
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="true_false_answer" value="0" id="true_option" checked>
                                <label for="true_option">Σωστό</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="true_false_answer" value="1" id="false_option">
                                <label for="false_option">Λάθος</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Συμπλήρωση κενών -->
                <div class="question-answers" id="fill_in_blank_answers" style="display: none;">
                    <p class="help-text">Προσθέστε αποδεκτές απαντήσεις για κάθε κενό. Στο κείμενο της ερώτησης χρησιμοποιήστε [blank] για κάθε κενό.</p>
                    <div id="fill_in_blank_container" class="answers-container">
                        <div class="answer-entry">
                            <label>Κενό #1:</label>
                            <input type="text" name="blank_answers[]" placeholder="Αποδεκτή απάντηση" class="answer-text">
                            <button type="button" class="remove-answer">❌</button>
                        </div>
                    </div>
                    <button type="button" class="add-answer-btn" data-target="fill_in_blank_container">+ Προσθήκη Επιπλέον Κενού</button>
                </div>
                
                <!-- Αντιστοίχισης -->
                <div class="question-answers" id="matching_answers" style="display: none;">
                    <p class="help-text">Προσθέστε ζεύγη αντιστοίχισης.</p>
                    <div id="matching_container" class="answers-container">
                        <div class="matching-pair">
                            <div class="matching-left">
                                <input type="text" name="matching_left[]" placeholder="Στοιχείο αριστερά" class="answer-text">
                            </div>
                            <div class="matching-connector">⟷</div>
                            <div class="matching-right">
                                <input type="text" name="matching_right[]" placeholder="Στοιχείο δεξιά" class="answer-text">
                            </div>
                            <button type="button" class="remove-pair">❌</button>
                        </div>
                    </div>
                    <button type="button" class="add-matching-pair">+ Προσθήκη Ζεύγους</button>
                </div>
                
                <!-- Ταξινόμησης -->
                <div class="question-answers" id="ordering_answers" style="display: none;">
                    <p class="help-text">Προσθέστε στοιχεία στη σωστή σειρά (από πάνω προς τα κάτω).</p>
                    <div id="ordering_container" class="answers-container">
                        <div class="ordering-item">
                            <span class="drag-handle">⋮⋮</span>
                            <input type="text" name="ordering_items[]" placeholder="Στοιχείο 1" class="answer-text">
                            <button type="button" class="remove-item">❌</button>
                        </div>
                        <div class="ordering-item">
                            <span class="drag-handle">⋮⋮</span>
                            <input type="text" name="ordering_items[]" placeholder="Στοιχείο 2" class="answer-text">
                            <button type="button" class="remove-item">❌</button>
                        </div>
                    </div>
                    <button type="button" class="add-ordering-item">+ Προσθήκη Στοιχείου</button>
                </div>
                
                <!-- Σύντομης απάντησης -->
                <div class="question-answers" id="short_answer_answers" style="display: none;">
                    <p class="help-text">Εισάγετε το μοντέλο απάντησης.</p>
                    <div class="answers-container">
                        <textarea name="model_answer" placeholder="Μοντέλο απάντησης (αποδεκτή απάντηση)" rows="3" class="answer-text-large"></textarea>
                    </div>
                </div>
                
                <!-- Ανάπτυξης -->
                <div class="question-answers" id="essay_answers" style="display: none;">
                    <p class="help-text">Εισάγετε ενδεικτική απάντηση.</p>
                    <div class="answers-container">
                        <textarea name="model_answer" placeholder="Ενδεικτική απάντηση ή οδηγίες βαθμολόγησης" rows="5" class="answer-text-large"></textarea>
                    </div>
                </div>
                </div>
                
                <!-- Επεξήγηση ερώτησης -->
                <div class="form-section">
                    <h3>Επεξήγηση</h3>
                    <div class="form-group">
                        <label for="explanation">Επεξήγηση Ερώτησης:</label>
                        <textarea name="explanation" id="explanation" rows="4" placeholder="Προσθέστε μια επεξήγηση για τη σωστή απάντηση"></textarea>
                    </div>
                </div>
                
                <!-- Κουμπιά υποβολής -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary">💾 Αποθήκευση</button>
                    <button type="submit" name="stay_on_page" value="1" class="btn-secondary">💾 Αποθήκευση & Νέα Ερώτηση</button>
                    <a href="manage_questions.php" class="btn-link">Ακύρωση</a>
                </div>
            </form>
        </div>
        
        <div class="tab-content" id="bulk-import-tab">
            <div class="info-banner">
                <p>Η μαζική εισαγωγή επιτρέπει την προσθήκη πολλών ερωτήσεων από αρχείο CSV.</p>
                <a href="bulk_import.php" class="btn-primary">Μετάβαση στη Μαζική Εισαγωγή</a>
            </div>
        </div>
    </main>
    
    <?php require_once '../includes/admin_footer.php'; ?>
    
    <script src="<?= BASE_URL ?>/admin/assets/js/enhanced_questions.js"></script>
</body>
</html>