<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Αρχικοποίηση μεταβλητών
$import_success = false;
$import_error = '';
$total_imported = 0;
$failed_imports = 0;
$successfulImports = [];
$errorLines = [];

// Ανάκτηση κατηγοριών
$categories_query = "SELECT id, name FROM test_categories ORDER BY name";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Χειρισμός της φόρμας εισαγωγής
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $subcategory_id = isset($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : 0;
        $chapter_id = isset($_POST['chapter_id']) ? intval($_POST['chapter_id']) : 0;
        $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'] : ';';
        
        // Έλεγχος εγκυρότητας
        if ($category_id === 0 || $subcategory_id === 0 || $chapter_id === 0) {
            $import_error = "Παρακαλώ επιλέξτε κατηγορία, υποκατηγορία και κεφάλαιο.";
        } else {
            // Επεξεργασία του CSV
            $file = $_FILES['csv_file']['tmp_name'];
            
            // Επεξεργασία του ZIP (αν υπάρχει)
            $media_files = [];
            if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = BASE_PATH . '/admin/test/uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $zip = new ZipArchive();
                if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
                    $zip->extractTo($uploadDir);
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        $media_files[$filename] = $uploadDir . $filename;
                    }
                    $zip->close();
                } else {
                    $import_error = "Σφάλμα κατά την εξαγωγή του αρχείου ZIP.";
                }
            }
            
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Ανάγνωση της πρώτης γραμμής (επικεφαλίδες)
                $headers = fgetcsv($handle, 0, $delimiter);
                
                // Έλεγχος κωδικοποίησης και μετατροπή, αν χρειάζεται
                $headers = array_map(function($header) {
                    return mb_convert_encoding($header, 'UTF-8', 'UTF-8, ISO-8859-7, Windows-1253');
                }, $headers);
                
                // Έλεγχος δομής αρχείου
                $valid_format = false;
                if (count($headers) >= 3) {
                    // Αναμενόμενες επικεφαλίδες για το νέο format: Ερώτηση, Επεξήγηση, Σωστή απάντηση, Απάντηση 1, Απάντηση 2...
                    if (stripos($headers[0], 'ερώτηση') !== false || stripos($headers[0], 'question') !== false) {
                        $valid_format = true;
                    }
                }
                
                if (!$valid_format) {
                    $import_error = "Μη έγκυρη μορφή CSV. Βεβαιωθείτε ότι η πρώτη γραμμή περιέχει τις επικεφαλίδες: Ερώτηση, Επεξήγηση, Σωστή απάντηση, Απάντηση 1, ...";
                } else {
                    $line_number = 1; // Ξεκινάμε από 1 για να συμπεριλάβουμε την επικεφαλίδα
                    
                    // Επεξεργασία κάθε γραμμής
                    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                        $line_number++;
                        
                        // Διορθώνουμε την κωδικοποίηση των δεδομένων
                        $data = array_map(function($cell) {
                            return mb_convert_encoding($cell, 'UTF-8', 'UTF-8, ISO-8859-7, Windows-1253');
                        }, $data);
                        
                        // Έλεγχος αν έχουμε αρκετά δεδομένα
                        if (count($data) < 3) {
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => "Ανεπαρκή δεδομένα στη γραμμή",
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                            continue;
                        }
                        
                        // Προετοιμασία δεδομένων
                        $question_text = trim($data[0]);
                        $explanation = isset($data[1]) ? trim($data[1]) : '';
                        $correct_answer_index = intval($data[2]) - 1; // Μετατροπή από 1-based σε 0-based
                        
                        // Έλεγχος εγκυρότητας βασικών δεδομένων
                        if (empty($question_text) || $correct_answer_index < 0 || $correct_answer_index >= count($data) - 3) {
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => "Λανθασμένος δείκτης σωστής απάντησης ή κενή ερώτηση",
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                            continue;
                        }
                        
                        // Συλλογή των απαντήσεων
                        $answers = array_slice($data, 3);
                        if (empty($answers)) {
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => "Δεν βρέθηκαν απαντήσεις",
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                            continue;
                        }
                        
                        // Εύρεση εικόνων από το ZIP (αν υπάρχουν)
                        $question_media = '';
                        $explanation_media = '';
                        $answer_media = [];
                        
                        // Χρήση του αριθμού γραμμής ως αναγνωριστικό για τις εικόνες
                        $media_prefix = $line_number - 1; // -1 για να λάβουμε υπόψη την επικεφαλίδα
                        
                        if (!empty($media_files)) {
                            // Έλεγχος για εικόνα ερώτησης (διάφορα πιθανά ονόματα)
                            foreach (["question_$media_prefix.png", "question_$media_prefix.jpg", "q_$media_prefix.png", "q_$media_prefix.jpg"] as $possible_name) {
                                if (isset($media_files[$possible_name])) {
                                    $question_media = $possible_name;
                                    break;
                                }
                            }
                            
                            // Έλεγχος για εικόνα επεξήγησης
                            foreach (["explanation_$media_prefix.png", "explanation_$media_prefix.jpg", "exp_$media_prefix.png", "exp_$media_prefix.jpg"] as $possible_name) {
                                if (isset($media_files[$possible_name])) {
                                    $explanation_media = $possible_name;
                                    break;
                                }
                            }
                            
                            // Έλεγχος για εικόνες απαντήσεων
                            foreach ($answers as $index => $answer) {
                                $answer_index = $index + 1;
                                foreach (["answer_{$media_prefix}_{$answer_index}.png", "answer_{$media_prefix}_{$answer_index}.jpg", "a_{$media_prefix}_{$answer_index}.png", "a_{$media_prefix}_{$answer_index}.jpg"] as $possible_name) {
                                    if (isset($media_files[$possible_name])) {
                                        $answer_media[$index] = $possible_name;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Έναρξη συναλλαγής
                        $mysqli->begin_transaction();
                        
                        try {
                            // Εισαγωγή ερώτησης
                            $question_type = 'single_choice'; // Προεπιλογή: μονής επιλογής
                            $author_id = $_SESSION['user_id'];
                            $status = 'active';
                            
                            $question_query = "INSERT INTO questions (chapter_id, author_id, question_text, question_explanation, question_type, question_media, explanation_media, status) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $question_stmt = $mysqli->prepare($question_query);
                            $question_stmt->bind_param("iisssss", $chapter_id, $author_id, $question_text, $explanation, $question_type, $question_media, $explanation_media, $status);
                            
                            if ($question_stmt->execute()) {
                                $question_id = $question_stmt->insert_id;
                                
                                // Εισαγωγή απαντήσεων
                                $answer_success = true;
                                foreach ($answers as $index => $answer_text) {
                                    if (empty(trim($answer_text))) continue;
                                    
                                    $is_correct = ($index == $correct_answer_index) ? 1 : 0;
                                    $current_answer_media = isset($answer_media[$index]) ? $answer_media[$index] : '';
                                    
                                    $answer_query = "INSERT INTO test_answers (question_id, answer_text, is_correct, answer_media) VALUES (?, ?, ?, ?)";
                                    $answer_stmt = $mysqli->prepare($answer_query);
                                    $answer_stmt->bind_param("isis", $question_id, $answer_text, $is_correct, $current_answer_media);
                                    
                                    if (!$answer_stmt->execute()) {
                                        $answer_success = false;
                                        throw new Exception("Σφάλμα εισαγωγής απάντησης: " . $answer_stmt->error);
                                    }
                                }
                                
                                // Εάν όλα πήγαν καλά, επικύρωση της συναλλαγής
                                if ($answer_success) {
                                    $mysqli->commit();
                                    $total_imported++;
                                    $successfulImports[] = [
                                        'question_id' => $question_id,
                                        'question_text' => $question_text
                                    ];
                                } else {
                                    throw new Exception("Σφάλμα εισαγωγής απαντήσεων");
                                }
                            } else {
                                throw new Exception("Σφάλμα εισαγωγής ερώτησης: " . $question_stmt->error);
                            }
                        } catch (Exception $e) {
                            // Αναίρεση της συναλλαγής σε περίπτωση σφάλματος
                            $mysqli->rollback();
                            $errorLines[] = [
                                'line' => $line_number,
                                'error' => $e->getMessage(),
                                'data' => implode($delimiter, $data)
                            ];
                            $failed_imports++;
                        }
                    }
                    
                    fclose($handle);
                    
                    if ($total_imported > 0) {
                        $import_success = true;
                    }
                }
            } else {
                $import_error = "Δεν ήταν δυνατό το άνοιγμα του αρχείου.";
            }
        }
    } else {
        $import_error = "Σφάλμα κατά το ανέβασμα του αρχείου: " . $_FILES['csv_file']['error'];
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Μαζική Εισαγωγή Ερωτήσεων</title>
     <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/bulk_import.css">
</head>
<body>


<main class="admin-container">
    <h2 class="admin-title">📤 Μαζική Εισαγωγή Ερωτήσεων</h2>
    
    <?php if ($import_success): ?>
        <div class="alert success">
            <h3>✅ Η εισαγωγή ολοκληρώθηκε επιτυχώς!</h3>
            <p>Εισήχθησαν επιτυχώς <?= $total_imported ?> ερωτήσεις.</p>
            <?php if ($failed_imports > 0): ?>
                <p>Απέτυχε η εισαγωγή <?= $failed_imports ?> ερωτήσεων.</p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($successfulImports)): ?>
            <div class="result-section">
                <h3>Επιτυχείς Εισαγωγές</h3>
                <div class="success-list">
                    <?php foreach ($successfulImports as $import): ?>
                        <div class="success-item">
                            <span class="success-id">#<?= $import['question_id'] ?></span>
                            <span class="success-text"><?= htmlspecialchars($import['question_text']) ?></span>
                            <a href="edit_question.php?id=<?= $import['question_id'] ?>" class="btn-edit">✏️</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorLines)): ?>
            <div class="result-section error-section">
                <h3>Σφάλματα Εισαγωγής</h3>
                <div class="error-list">
                    <?php foreach ($errorLines as $error): ?>
                        <div class="error-item">
                            <div class="error-header">
                                <span class="error-line">Γραμμή <?= $error['line'] ?></span>
                                <span class="error-message"><?= htmlspecialchars($error['error']) ?></span>
                            </div>
                            <div class="error-data"><?= htmlspecialchars($error['data']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if (!empty($import_error)): ?>
        <div class="alert error"><?= $import_error ?></div>
    <?php endif; ?>
    
    <div class="admin-section">
        <h3>Ανέβασμα Αρχείου CSV</h3>
        
        <form method="POST" enctype="multipart/form-data" class="admin-form" id="bulk-import-form">
            <div class="form-group">
                <label for="category_id">Κατηγορία:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Επιλέξτε Κατηγορία --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subcategory_id">Υποκατηγορία:</label>
                <select id="subcategory_id" name="subcategory_id" required disabled>
                    <option value="">-- Επιλέξτε πρώτα Κατηγορία --</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="chapter_id">Κεφάλαιο:</label>
                <select id="chapter_id" name="chapter_id" required disabled>
                    <option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="delimiter">Διαχωριστικό:</label>
                <select id="delimiter" name="delimiter">
                    <option value=";">Ερωτηματικό (;)</option>
                    <option value=",">Κόμμα (,)</option>
                    <option value="\t">Tab</option>
                </select>
            </div>
            
            <div class="form-group full-width">
                <label for="csv_file">Αρχείο CSV:</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv, text/csv" required>
                <div id="csv-preview" style="display: none;"></div>
            </div>
            
            <div class="form-group full-width">
                <label for="zip_file">Αρχείο ZIP με εικόνες (προαιρετικό):</label>
                <input type="file" id="zip_file" name="zip_file" accept=".zip">
                <span class="file-help">Το αρχείο ZIP μπορεί να περιέχει εικόνες με ονόματα αρχείων της μορφής: question_1.png, question_2.png, explanation_1.png, answer_1_1.png, answer_1_2.png κλπ.</span>
            </div>
            
            <div class="form-info full-width">
                <h4>Οδηγίες Μορφής CSV:</h4>
                <p>Το αρχείο CSV πρέπει να έχει τις εξής στήλες:</p>
                <ol>
                    <li><strong>Ερώτηση:</strong> Το κείμενο της ερώτησης</li>
                    <li><strong>Επεξήγηση:</strong> Επεξήγηση της ερώτησης (προαιρετικό)</li>
                    <li><strong>Σωστή Απάντηση:</strong> Ο αριθμός της σωστής απάντησης (1, 2, 3, κλπ.)</li>
                    <li><strong>Απάντηση 1:</strong> Η πρώτη επιλογή απάντησης</li>
                    <li><strong>Απάντηση 2, 3, κλπ.:</strong> Επιπλέον επιλογές απάντησης</li>
                </ol>
                <p><strong>Παράδειγμα:</strong></p>
                <pre>Ερώτηση;Επεξήγηση;Σωστή απάντηση;Απάντηση 1;Απάντηση 2;Απάντηση 3
Ποια είναι η πρωτεύουσα της Ελλάδας;Η Αθήνα είναι η πρωτεύουσα της Ελλάδας από το 1834.;1;Αθήνα;Θεσσαλονίκη;Πάτρα</pre>
            </div>
            
            <div class="form-actions full-width">
                <button type="submit" class="btn-primary">📤 Εισαγωγή Ερωτήσεων</button>
                <a href="download_template.php" class="btn-secondary">📥 Κατέβασμα Προτύπου CSV</a>
                <a href="manage_questions.php" class="btn-secondary">🔙 Επιστροφή</a>
            </div>
        </form>
    </div>
    
    <div class="admin-section">
        <h3>Συμβουλές για επιτυχή εισαγωγή</h3>
        <div class="tips-container">
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Κωδικοποίηση αρχείου</h4>
                    <p>Βεβαιωθείτε ότι το αρχείο CSV είναι αποθηκευμένο σε κωδικοποίηση UTF-8 για σωστή εμφάνιση των ελληνικών χαρακτήρων.</p>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Εικόνες</h4>
                    <p>Οι εικόνες πρέπει να είναι σε μορφή PNG ή JPG και να έχουν όνομα αρχείου της μορφής: question_1.png, explanation_1.png, answer_1_1.png κλπ.</p>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">💡</div>
                <div class="tip-content">
                    <h4>Σωστή απάντηση</h4>
                    <p>Η "Σωστή απάντηση" πρέπει να είναι ο αριθμός της απάντησης (ξεκινώντας από το 1), όχι το κείμενο της απάντησης.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?= BASE_URL ?>/admin/assets/js/bulk_import.js"></script>

<?php require_once '../includes/admin_footer.php'; ?>
</body>
</html> 1:</strong> Η πρώτη επιλογή απάντησης</li>
                    <li><strong>Απάντηση