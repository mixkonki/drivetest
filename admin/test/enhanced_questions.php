while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
    try {
        // Εξαγωγή δεδομένων με βάση το mapping
        $question_text = isset($column_mapping['question_text']) ? clean_encoding($data[$column_mapping['question_text']]) : null;
        $category_name = isset($column_mapping['category']) ? clean_encoding($data[$column_mapping['category']]) : null;
        $subcategory_name = isset($column_mapping['subcategory']) ? clean_encoding($data[$column_mapping['subcategory']]) : null;
        $chapter_name = isset($column_mapping['chapter']) ? clean_encoding($data[$column_mapping['chapter']]) : null;
        $question_type = isset($column_mapping['question_type']) ? clean_encoding($data[$column_mapping['question_type']]) : $default_question_type;
        $explanation = isset($column_mapping['explanation']) ? clean_encoding($data[$column_mapping['explanation']]) : '';
        $difficulty = isset($column_mapping['difficulty']) ? intval($data[$column_mapping['difficulty']]) : 1;
        $tags = isset($column_mapping['tags']) ? clean_encoding($data[$column_mapping['tags']]) : '';
        
        // Έλεγχος για υποχρεωτικά πεδία
        if (empty($question_text)) {
            $error_count++;
            $errors[] = [
                'line' => $line_number,
                'message' => 'Το κείμενο ερώτησης είναι κενό.',
                'data' => implode($delimiter, $data)
            ];
            $line_number++;
            continue;
        }
        
        // Προσδιορισμός ή δημιουργία κατηγορίας/υποκατηγορίας/κεφαλαίου
        $category_id = $default_category_id;
        $subcategory_id = $default_subcategory_id;
        $chapter_id = $default_chapter_id;
        
        if ($create_missing) {
            // Αν έχουμε όνομα κατηγορίας, ελέγχουμε αν υπάρχει
            if (!empty($category_name)) {
                $stmt = $mysqli->prepare("SELECT id FROM test_categories WHERE name = ?");
                $stmt->bind_param("s", $category_name);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($category_id);
                    $stmt->fetch();
                } else {
                    // Δημιουργία νέας κατηγορίας
                    $insert_stmt = $mysqli->prepare("INSERT INTO test_categories (name) VALUES (?)");
                    $insert_stmt->bind_param("s", $category_name);
                    
                    if ($insert_stmt->execute()) {
                        $category_id = $insert_stmt->insert_id;
                        $created_categories[] = [
                            'id' => $category_id,
                            'name' => $category_name
                        ];
                        $log_messages[] = "Δημιουργήθηκε νέα κατηγορία: '$category_name' με ID: $category_id";
                    } else {
                        throw new Exception("Αποτυχία δημιουργίας κατηγορίας: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                }
                $stmt->close();
            }
            
            // Αν έχουμε όνομα υποκατηγορίας και έγκυρο category_id, ελέγχουμε αν υπάρχει
            if (!empty($subcategory_name) && $category_id > 0) {
                $stmt = $mysqli->prepare("SELECT id FROM test_subcategories WHERE name = ? AND test_category_id = ?");
                $stmt->bind_param("si", $subcategory_name, $category_id);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($subcategory_id);
                    $stmt->fetch();
                } else {
                    // Δημιουργία νέας υποκατηγορίας
                    $insert_stmt = $mysqli->prepare("INSERT INTO test_subcategories (name, test_category_id) VALUES (?, ?)");
                    $insert_stmt->bind_param("si", $subcategory_name, $category_id);
                    
                    if ($insert_stmt->execute()) {
                        $subcategory_id = $insert_stmt->insert_id;
                        $created_subcategories[] = [
                            'id' => $subcategory_id,
                            'name' => $subcategory_name,
                            'category_id' => $category_id
                        ];
                        $log_messages[] = "Δημιουργήθηκε νέα υποκατηγορία: '$subcategory_name' με ID: $subcategory_id (κατηγορία: $category_id)";
                    } else {
                        throw new Exception("Αποτυχία δημιουργίας υποκατηγορίας: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                }
                $stmt->close();
            }
            
            // Αν έχουμε όνομα κεφαλαίου και έγκυρο subcategory_id, ελέγχουμε αν υπάρχει
            if (!empty($chapter_name) && $subcategory_id > 0) {
                $stmt = $mysqli->prepare("SELECT id FROM test_chapters WHERE name = ? AND subcategory_id = ?");
                $stmt->bind_param("si", $chapter_name, $subcategory_id);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($chapter_id);
                    $stmt->fetch();
                } else {
                    // Δημιουργία νέου κεφαλαίου
                    $insert_stmt = $mysqli->prepare("INSERT INTO test_chapters (name, subcategory_id) VALUES (?, ?)");
                    $insert_stmt->bind_param("si", $chapter_name, $subcategory_id);
                    
                    if ($insert_stmt->execute()) {
                        $chapter_id = $insert_stmt->insert_id;
                        $created_chapters[] = [
                            'id' => $chapter_id,
                            'name' => $chapter_name,
                            'subcategory_id' => $subcategory_id
                        ];
                        $log_messages[] = "Δημιουργήθηκε νέο κεφάλαιο: '$chapter_name' με ID: $chapter_id (υποκατηγορία: $subcategory_id)";
                    } else {
                        throw new Exception("Αποτυχία δημιουργίας κεφαλαίου: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                }
                $stmt->close();
            }
        }
        
        // Έλεγχος αν έχουμε έγκυρο κεφάλαιο
        if ($chapter_id <= 0) {
            $error_count++;
            $errors[] = [
                'line' => $line_number,
                'message' => 'Δεν ορίστηκε έγκυρο κεφάλαιο. Ελέγξτε την αντιστοίχιση ή ενεργοποιήστε την αυτόματη δημιουργία.',
                'data' => implode($delimiter, $data)
            ];
            $line_number++;
            continue;
        }
        
        // Προσδιορισμός του τύπου ερώτησης
        if (!empty($question_type) && !is_numeric($question_type)) {
            // Αν είναι κείμενο, αντιστοιχίζουμε με γνωστούς τύπους
            $type_mapping = [
                'μονή επιλογή' => 'single_choice',
                'πολλαπλή επιλογή' => 'multiple_choice',
                'πολλαπλές επιλογές' => 'multiple_choice',
                'σωστό/λάθος' => 'true_false',
                'true/false' => 'true_false',
                'σωστό λάθος' => 'true_false',
                'αντιστοίχιση' => 'matching',
                'ταξινόμηση' => 'ordering',
                'συμπλήρωση κενών' => 'fill_in_blank',
                'κενά' => 'fill_in_blank',
                'σύντομη απάντηση' => 'short_answer',
                'ανάπτυξη' => 'essay'
            ];
            
            $question_type_lower = strtolower($question_type);
            foreach ($type_mapping as $key => $value) {
                if (strpos($question_type_lower, $key) !== false) {
                    $question_type = $value;
                    break;
                }
            }
            
            // Αν δεν βρέθηκε αντιστοίχιση, χρησιμοποιούμε το προεπιλεγμένο
            if (!in_array($question_type, array_keys($GLOBALS['question_types']))) {
                $question_type = $default_question_type;
            }
        }
        
        // Συλλογή και επεξεργασία απαντήσεων
        $answers = [];
        $correct_answers = [];
        
        // Ανάλογα με τον τύπο ερώτησης, συλλέγουμε τις απαντήσεις
        if ($question_type === 'single_choice' || $question_type === 'multiple_choice') {
            // Εντοπισμός της σωστής απάντησης
            $correct_answer_index = null;
            if (isset($column_mapping['correct_answer'])) {
                $correct_answer_value = clean_encoding($data[$column_mapping['correct_answer']]);
                
                // Αν η σωστή απάντηση είναι αριθμός (π.χ. 1, 2, 3), το εντοπίζουμε
                if (is_numeric($correct_answer_value)) {
                    $correct_answer_index = intval($correct_answer_value) - 1; // μετατροπή σε 0-based
                } else {
                    // Αν είναι κείμενο, θα το συγκρίνουμε με τις απαντήσεις αργότερα
                    $correct_answer_text = $correct_answer_value;
                }
            }
            
            // Συλλογή των απαντήσεων
            for ($i = 1; $i <= 6; $i++) {
                $answer_key = 'answer' . $i;
                if (isset($column_mapping[$answer_key]) && !empty($data[$column_mapping[$answer_key]])) {
                    $answer_text = clean_encoding($data[$column_mapping[$answer_key]]);
                    $answers[] = $answer_text;
                    
                    // Αν έχουμε σωστή απάντηση με κείμενο, ελέγχουμε αν ταιριάζει
                    if (isset($correct_answer_text) && $answer_text === $correct_answer_text) {
                        $correct_answers[] = count($answers) - 1; // 0-based index
                    }
                }
            }
            
            // Αν έχουμε σωστή απάντηση με αριθμό, την προσθέτουμε στις σωστές
            if ($correct_answer_index !== null && $correct_answer_index >= 0 && $correct_answer_index < count($answers)) {
                $correct_answers[] = $correct_answer_index;
            }
            
            // Αν δεν έχουμε σωστές απαντήσεις για single_choice, ορίζουμε την πρώτη ως σωστή
            if ($question_type === 'single_choice' && empty($correct_answers) && !empty($answers)) {
                $correct_answers[] = 0;
            }
        } elseif ($question_type === 'true_false') {
            // Για true/false, έχουμε δύο απαντήσεις
            $answers = ['Σωστό', 'Λάθος'];
            
            // Προσδιορισμός της σωστής απάντησης
            $correct_answer = '0'; // Προεπιλογή: Σωστό
            if (isset($column_mapping['correct_answer'])) {
                $correct_answer_value = strtolower(clean_encoding($data[$column_mapping['correct_answer']]));
                
                if (in_array($correct_answer_value, ['λάθος', 'false', 'λ', 'f', '0', 'λαθοσ'])) {
                    $correct_answer = '1'; // 1 = Λάθος
                }
            }
            
            $correct_answers[] = $correct_answer;
        } elseif ($question_type === 'fill_in_blank') {
            // Για συμπλήρωση κενών, χρειαζόμαστε τις αποδεκτές απαντήσεις
            // Συνήθως βρίσκονται σε στήλη 'correct_answer'
            if (isset($column_mapping['correct_answer'])) {
                $correct_answer_value = clean_encoding($data[$column_mapping['correct_answer']]);
                
                // Αν περιέχει διαχωριστικό όπως κόμμα ή ερωτηματικό, το διαχωρίζουμε
                if (strpos($correct_answer_value, ',') !== false) {
                    $answer_parts = explode(',', $correct_answer_value);
                    foreach ($answer_parts as $part) {
                        $answer_text = trim($part);
                        if (!empty($answer_text)) {
                            $answers[] = $answer_text;
                            $correct_answers[] = count($answers) - 1;
                        }
                    }
                } else {
                    $answers[] = $correct_answer_value;
                    $correct_answers[] = 0;
                }
            }
            
            // Επίσης ελέγχουμε για απαντήσεις από τα πεδία answerX
            for ($i = 1; $i <= 6; $i++) {
                $answer_key = 'answer' . $i;
                if (isset($column_mapping[$answer_key]) && !empty($data[$column_mapping[$answer_key]])) {
                    $answer_text = clean_encoding($data[$column_mapping[$answer_key]]);
                    $answers[] = $answer_text;
                    $correct_answers[] = count($answers) - 1;
                }
            }
        } else {
            // Για τους άλλους τύπους ερωτήσεων, απλή συλλογή απαντήσεων
            for ($i = 1; $i <= 6; $i++) {
                $answer_key = 'answer' . $i;
                if (isset($column_mapping[$answer_key]) && !empty($data[$column_mapping[$answer_key]])) {
                    $answer_text = clean_encoding($data[$column_mapping[$answer_key]]);
                    $answers[] = $answer_text;
                }
            }
            
            // Για σύντομη απάντηση και ανάπτυξη, η πρώτη είναι το μοντέλο απάντησης
            if (($question_type === 'short_answer' || $question_type === 'essay') && !empty($answers)) {
                $correct_answers[] = 0;
            }
        }
        
        // Έλεγχος αν έχουμε απαντήσεις
        if (empty($answers) && $question_type !== 'short_answer' && $question_type !== 'essay') {
            $error_count++;
            $errors[] = [
                'line' => $line_number,
                'message' => 'Δεν βρέθηκαν απαντήσεις για την ερώτηση.',
                'data' => implode($delimiter, $data)
            ];
            $line_number++;
            continue;
        }
        
        // Επεξεργασία των tags
        $tags_array = !empty($tags) ? explode(',', $tags) : [];
        $tags_json = json_encode(array_map('trim', $tags_array));
        
        // Αποθήκευση της ερώτησης
        $author_id = $_SESSION['user_id'] ?? 1;
        $query = "INSERT INTO questions (chapter_id, question_text, question_explanation, question_type, 
                    author_id, status, difficulty_level, tags, created_at) 
                  VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW())";
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("isssiis", $chapter_id, $question_text, $explanation, $question_type, 
                        $author_id, $difficulty, $tags_json);
        
        if ($stmt->execute()) {
            $question_id = $stmt->insert_id;
            
            // Αποθήκευση των απαντήσεων
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
            
            // Επιτυχής εισαγωγή
            $success_count++;
            $imported_questions[] = [
                'id' => $question_id,
                'text' => $question_text,
                'category' => $category_name,
                'subcategory' => $subcategory_name,
                'chapter' => $chapter_name
            ];
            
            $log_messages[] = "Εισήχθη ερώτηση ID: $question_id - από γραμμή: $line_number";
        } else {
            throw new Exception("Αποτυχία εισαγωγής ερώτησης: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_count++;
        $errors[] = [
            'line' => $line_number,
            'message' => $e->getMessage(),
            'data' => implode($delimiter, $data)
        ];
        log_debug("Error on line $line_number: " . $e->getMessage());
    }
    
    $line_number++;
}

fclose($handle);

return $result;
}

// Αν είναι υποβολή φόρμας για προεπισκόπηση
$preview_data = [];
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['preview_csv'])) {
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['csv_file']['tmp_name'];
    $delimiter = $_POST['delimiter'] ?? ';';
    $preview_data = analyzeCSV($file, $delimiter);
}
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<title>Μαζική Εισαγωγή Ερωτήσεων</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/test_styles.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/enhanced_question.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/bulk_import.css">
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
<main class="admin-container">
<h2>📥 Μαζική Εισαγωγή Ερωτήσεων</h2>

<?php if (!empty($error_message)): ?>
<div class="alert alert-error">
    <p><?= $error_message ?></p>
</div>
<?php endif; ?>

<?php if ($success_count > 0): ?>
<div class="alert alert-success">
    <h3>✅ Η εισαγωγή ολοκληρώθηκε!</h3>
    <p>Εισήχθησαν επιτυχώς <?= $success_count ?> ερωτήσεις. Υπήρξαν <?= $error_count ?> σφάλματα.</p>
</div>
<?php endif; ?>

<?php if (empty($preview_data) && empty($imported_questions)): ?>
<!-- Αρχική φόρμα για ανέβασμα CSV -->
<div class="form-section">
    <h3>Ανέβασμα Αρχείου CSV</h3>
    <form method="POST" enctype="multipart/form-data" class="admin-form" id="preview-form">
        <input type="hidden" name="preview_csv" value="1">
        
        <div class="form-group">
            <label for="csv_file">Επιλογή Αρχείου CSV:</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            <p class="help-text">Επιλέξτε αρχείο CSV με τις ερωτήσεις προς εισαγωγή.</p>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="delimiter">Διαχωριστικό:</label>
                <select name="delimiter" id="delimiter">
                    <option value=";">Ερωτηματικό (;)</option>
                    <option value=",">Κόμμα (,)</option>
                    <option value="\t">Tab</option>
                    <option value="|">Pipe (|)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="csv_has_headers">Το αρχείο περιέχει επικεφαλίδες:</label>
                <div class="checkbox-container">
                    <input type="checkbox" name="csv_has_headers" id="csv_has_headers" checked>
                    <label for="csv_has_headers" class="checkbox-label">Ναι, η πρώτη γραμμή περιέχει ονόματα στηλών</label>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">🔍 Προεπισκόπηση CSV</button>
        </div>
    </form>
</div>

<div class="form-section">
    <h3>Οδηγίες Μορφής CSV</h3>
    
    <div class="form-info">
        <h4>Προτεινόμενες Επικεφαλίδες</h4>
        <p>Για καλύτερη αυτόματη αντιστοίχιση, προτείνεται να περιέχει το αρχείο CSV τις παρακάτω στήλες:</p>
        <ul>
            <li><strong>Κατηγορία</strong> - Η κύρια κατηγορία της ερώτησης</li>
            <li><strong>Υποκατηγορία</strong> - Η υποκατηγορία της ερώτησης</li>
            <li><strong>Κεφάλαιο</strong> - Το κεφάλαιο όπου ανήκει η ερώτηση</li>
            <li><strong>Ερώτηση</strong> - Το κείμενο της ερώτησης</li>
            <li><strong>Τύπος</strong> - Ο τύπος της ερώτησης (π.χ. single_choice, multiple_choice)</li>
            <li><strong>Επεξήγηση</strong> - Επεξήγηση της σωστής απάντησης</li>
            <li><strong>Σωστή</strong> - Η σωστή απάντηση (αριθμός ή κείμενο)</li>
            <li><strong>Απάντηση1</strong> έως <strong>Απάντηση6</strong> - Οι πιθανές απαντήσεις</li>
            <li><strong>Δυσκολία</strong> - Επίπεδο δυσκολίας (1-3)</li>
            <li><strong>Ετικέτες</strong> - Λέξεις-κλειδιά διαχωρισμένες με κόμμα</li>
        </ul>
        
        <h4>Προκαθορισμένη Μορφή</h4>
        <p>Αν επιλέξετε "Προκαθορισμένη μορφή", το CSV αναμένεται να έχει τις στήλες με την παρακάτω σειρά:</p>
        <pre>Κατηγορία;Υποκατηγορία;Κεφάλαιο;Ερώτηση;Τύπος;Επεξήγηση;Σωστή;Απάντηση1;Απάντηση2;Απάντηση3;Απάντηση4;Απάντηση5;Απάντηση6;Δυσκολία;Ετικέτες</pre>
    </div>
</div>

<?php elseif (!empty($preview_data)): ?>
<!-- Φόρμα αντιστοίχισης και εισαγωγής μετά την προεπισκόπηση -->
<div class="form-section">
    <h3>Προεπισκόπηση CSV</h3>
    
    <div class="preview-table-container">
        <table class="preview-table">
            <thead>
                <tr>
                    <th>#</th>
                    <?php foreach ($preview_data['headers'] as $index => $header): ?>
                    <th><?= htmlspecialchars($header) ?> <span class="column-index">[<?= $index ?>]</span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview_data['sample_data'] as $row_index => $row): ?>
                <tr>
                    <td><?= $row_index + 1 ?></td>
                    <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="form-section">
    <h3>Ρυθμίσεις Εισαγωγής</h3>
    
    <form method="POST" enctype="multipart/form-data" class="admin-form">
        <!-- Αντιγραφή του επιλεγμένου αρχείου και ρυθμίσεων -->
        <input type="hidden" name="csv_has_headers" value="<?= isset($_POST['csv_has_headers']) ? '1' : '0' ?>">
        <input type="hidden" name="delimiter" value="<?= htmlspecialchars($_POST['delimiter'] ?? ';') ?>">
        
        <div class="form-group">
            <label for="import_mode">Μέθοδος Αντιστοίχισης:</label>
            <select name="import_mode" id="import_mode" required>
                <?php foreach ($import_modes as $value => $label): ?>
                <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="manual-mapping" class="mapping-section">
            <h4>Αντιστοίχιση Στηλών</h4>
            <p class="help-text">Επιλέξτε ποια στήλη του CSV αντιστοιχεί σε κάθε πεδίο της βάσης δεδομένων.</p>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="map_question_text">Κείμενο Ερώτησης:</label>
                    <select name="map_question_text" id="map_question_text" required>
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'ερώτη') !== false || stripos($header, 'quest') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="map_category">Κατηγορία:</label>
                    <select name="map_category" id="map_category">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'κατηγορ') !== false || stripos($header, 'categor') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="map_subcategory">Υποκατηγορία:</label>
                    <select name="map_subcategory" id="map_subcategory">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'υποκατηγορ') !== false || stripos($header, 'subcategor') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="map_chapter">Κεφάλαιο:</label>
                    <select name="map_chapter" id="map_chapter">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'κεφάλαι') !== false || stripos($header, 'chapter') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="map_question_type">Τύπος Ερώτησης:</label>
                    <select name="map_question_type" id="map_question_type">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'τύπο') !== false || stripos($header, 'type') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="map_explanation">Επεξήγηση:</label>
                    <select name="map_explanation" id="map_explanation">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'επεξήγησ') !== false || stripos($header, 'explan') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="map_correct_answer">Σωστή Απάντηση:</label>
                    <select name="map_correct_answer" id="map_correct_answer">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'σωστ') !== false || stripos($header, 'correct') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="map_difficulty">Δυσκολία:</label>
                    <select name="map_difficulty" id="map_difficulty">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'δυσκολ') !== false || stripos($header, 'diffic') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="map_tags">Ετικέτες:</label>
                    <select name="map_tags" id="map_tags">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'ετικέτ') !== false || stripos($header, 'tag') !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <h4>Αντιστοίχιση Απαντήσεων</h4>
            <div class="form-row">
                <?php for($i = 1; $i <= 6; $i++): ?>
                <div class="form-group">
                    <label for="map_answer<?= $i ?>">Απάντηση <?= $i ?>:</label>
                    <select name="map_answer<?= $i ?>" id="map_answer<?= $i ?>">
                        <option value="">-- Επιλέξτε Στήλη --</option>
                        <?php foreach ($preview_data['headers'] as $index => $header): ?>
                        <option value="<?= $index ?>" <?= stripos($header, 'απάντηση'.$i) !== false || stripos($header, 'answer'.$i) !== false ? 'selected' : '' ?>>
                            <?= htmlspecialchars($header) ?> [<?= $index ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($i % 3 == 0): ?>
                </div><div class="form-row">
                <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Προεπιλεγμένες Τιμές & Ρυθμίσεις</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="default_category_id">Προεπιλεγμένη Κατηγορία:</label>
                    <select name="default_category_id" id="default_category_id">
                        <option value="">-- Καμία --</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-text">Θα χρησιμοποιηθεί αν δεν υπάρχει αντιστοιχισμένη στήλη ή τιμή.</p>
                </div>
                
                <div class="form-group">
                    <label for="default_subcategory_id">Προεπιλεγμένη Υποκατηγορία:</label>
                    <select name="default_subcategory_id" id="default_subcategory_id">
                        <option value="">-- Καμία --</option>
                        <?php foreach ($subcategories as $subcategory): ?>
                        <option value="<?= $subcategory['id'] ?>" data-category="<?= $subcategory['test_category_id'] ?>">
                            <?= htmlspecialchars($subcategory['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="default_chapter_id">Προεπιλεγμένο Κεφάλαιο:</label>
                    <select name="default_chapter_id" id="default_chapter_id">
                        <option value="">-- Κανένα --</option>
                        <?php foreach ($chapters as $chapter): ?>
                        <option value="<?= $chapter['id'] ?>" data-subcategory="<?= $chapter['subcategory_id'] ?>">
                            <?= htmlspecialchars($chapter['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="default_question_type">Προεπιλεγμένος Τύπος Ερώτησης:</label>
                    <select name="default_question_type" id="default_question_type">
                        <?php foreach ($question_types as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $value === 'single_choice' ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help-text">Θα χρησιμοποιηθεί αν δεν υπάρχει αντιστοιχισμένη στήλη ή τιμή.</p>
                </div>
                
                <div class="form-group">
                    <label for="create_missing">Αυτόματη Δημιουργία Κατηγοριών/Υποκατηγοριών/Κεφαλαίων:</label>
                    <div class="checkbox-container">
                        <input type="checkbox" name="create_missing" id="create_missing" checked>
                        <label for="create_missing" class="checkbox-label">Δημιουργία νέων εγγραφών όταν δεν υπάρχουν</label>
                    </div>
                    <p class="help-text">Αν ενεργοποιηθεί, θα δημιουργηθούν αυτόματα νέες κατηγορίες, υποκατηγορίες και κεφάλαια όταν δεν βρεθούν.</p>
                </div>
            </div>
        </div>
        
        <!-- Επαναφόρτωση του αρχείου -->
        <div class="form-group hidden">
            <input type="file" name="csv_file" id="csv_file_hidden">
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">📥 Εισαγωγή Ερωτήσεων</button>
            <a href="bulk_import.php" class="btn-secondary">🔄 Νέα Εισαγωγή</a>
        </div>
    </form>
</div>

<script>
    // Αντιγραφή του αρχείου από το preview form
    document.addEventListener('DOMContentLoaded', function() {
        const previewFileInput = document.getElementById('csv_file');
        const hiddenFileInput = document.getElementById('csv_file_hidden');
        
        if (previewFileInput && hiddenFileInput && previewFileInput.files[0]) {
            // Δημιουργία ενός νέου FileList αντικειμένου (δεν είναι εύκολο, οπότε χρησιμοποιούμε dataTransfer)
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(previewFileInput.files[0]);
            hiddenFileInput.files = dataTransfer.files;
        }
    });
</script>

<?php elseif (!empty($imported_questions)): ?>
<!-- Αποτελέσματα εισαγωγής -->
<div class="result-section">
    <h3>Αποτελέσματα Εισαγωγής</h3>
    
    <div class="result-summary">
        <p>Συνολικές ερωτήσεις που εισήχθησαν: <strong><?= $success_count ?></strong></p>
        <p>Σφάλματα: <strong><?= $error_count ?></strong></p>
    </div>
    
    <?php if (!empty($created_categories) || !empty($created_subcategories) || !empty($created_chapters)): ?>
    <div class="created-items">
        <h4>Νέες Εγγραφές που Δημιουργήθηκαν</h4>
        
        <?php if (!empty($created_categories)): ?>
        <div class="created-group">
            <h5>Κατηγορίες</h5>
            <ul>
                <?php foreach ($created_categories as $category): ?>
                <li><?= htmlspecialchars($category['name']) ?> (ID: <?= $category['id'] ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($created_subcategories)): ?>
        <div class="created-group">
            <h5>Υποκατηγορίες</h5>
            <ul>
                <?php foreach ($created_subcategories as $subcategory): ?>
                <li><?= htmlspecialchars($subcategory['name']) ?> (ID: <?= $subcategory['id'] ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($created_chapters)): ?>
        <div class="created-group">
            <h5>Κεφάλαια</h5>
            <ul>
                <?php foreach ($created_chapters as $chapter): ?>
                <li><?= htmlspecialchars($chapter['name']) ?> (ID: <?= $chapter['id'] ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="imported-questions">
        <h4>Εισαχθείσες Ερωτήσεις</h4>
        <div class="success-list">
            <?php foreach ($imported_questions as $question): ?>
            <div class="success-item">
                <span class="success-id"><?= $question['id'] ?></span>
                <span class="success-text"><?= htmlspecialchars($question['text']) ?></span>
                <a href="edit_question.php?id=<?= $question['id'] ?>" class="btn-edit">✏️</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="error-section">
        <h4>Σφάλματα</h4>
        <div class="error-list">
            <?php foreach ($errors as $error): ?>
            <div class="error-item">
                <div class="error-header">
                    <span class="error-line">Γραμμή <?= $error['line'] ?></span>
                    <span class="error-message"><?= htmlspecialchars($error['message']) ?></span>
                </div>
                <div class="error-data"><?= htmlspecialchars($error['data']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="form-actions">
        <a href="bulk_import.php" class="btn-primary">🔄 Νέα Εισαγωγή</a>
        <a href="manage_questions.php" class="btn-secondary">👈 Επιστροφή στη Διαχείριση</a>
    </div>
</div>
<?php endif; ?>

</main>

<?php require_once '../includes/admin_footer.php'; ?>

<script src="<?= BASE_URL ?>/admin/assets/js/bulk_import.js"></script>
</body>
</html>($handle);

$log_messages[] = "Ολοκληρώθηκε η επεξεργασία με $success_count επιτυχίες και $error_count σφάλματα.";
log_debug("Import completed with $success_count successes and $error_count errors.");
}

/**
* Επεξεργασία CSV με αυτόματη αντιστοίχιση επικεφαλίδων
*/
function processCSVAutoMapping($file, $create_missing, $default_category_id, 
                         $default_subcategory_id, $default_chapter_id, 
                         $default_question_type, $delimiter) {
global $mysqli, $success_count, $error_count, $errors, $imported_questions, 
       $created_categories, $created_subcategories, $created_chapters, $log_messages;

// Άνοιγμα του αρχείου CSV
$handle = fopen($file, 'r');
if ($handle === false) {
    $errors[] = "Αδυναμία ανάγνωσης του αρχείου CSV.";
    log_debug("Error: Unable to read CSV file.");
    return;
}

// Ανάγνωση της γραμμής επικεφαλίδων
$headers = fgetcsv($handle, 0, $delimiter);
if ($headers === false) {
    $errors[] = "Το αρχείο CSV είναι κενό ή δεν έχει επικεφαλίδες.";
    log_debug("Error: CSV file is empty or has no headers.");
    fclose($handle);
    return;
}

// Καθαρισμός επικεφαλίδων
$headers = array_map(function($header) {
    return strtolower(trim(clean_encoding($header)));
}, $headers);

// Αντιστοίχιση επικεφαλίδων σε πεδία
$column_mapping = [];
$field_mappings = [
    'category' => ['category', 'κατηγορία', 'κατηγορια', 'category_name'],
    'subcategory' => ['subcategory', 'υποκατηγορία', 'υποκατηγορια', 'subcategory_name'],
    'chapter' => ['chapter', 'κεφάλαιο', 'κεφαλαιο', 'chapter_name'],
    'question_text' => ['question', 'question_text', 'ερώτηση', 'ερωτηση', 'κείμενο ερώτησης', 'κειμενο ερωτησης'],
    'question_type' => ['type', 'question_type', 'τύπος', 'τυπος', 'τύπος ερώτησης', 'τυπος ερωτησης'],
    'explanation' => ['explanation', 'επεξήγηση', 'επεξηγηση', 'question_explanation'],
    'correct_answer' => ['correct', 'correct_answer', 'σωστή απάντηση', 'σωστη απαντηση', 'σωστη'],
    'answer1' => ['answer1', 'απάντηση1', 'απαντηση1', 'answer_1', 'απάντηση 1', 'απαντηση 1'],
    'answer2' => ['answer2', 'απάντηση2', 'απαντηση2', 'answer_2', 'απάντηση 2', 'απαντηση 2'],
    'answer3' => ['answer3', 'απάντηση3', 'απαντηση3', 'answer_3', 'απάντηση 3', 'απαντηση 3'],
    'answer4' => ['answer4', 'απάντηση4', 'απαντηση4', 'answer_4', 'απάντηση 4', 'απαντηση 4'],
    'answer5' => ['answer5', 'απάντηση5', 'απαντηση5', 'answer_5', 'απάντηση 5', 'απαντηση 5'],
    'answer6' => ['answer6', 'απάντηση6', 'απαντηση6', 'answer_6', 'απάντηση 6', 'απαντηση 6'],
    'difficulty' => ['difficulty', 'δυσκολία', 'δυσκολια', 'level', 'επίπεδο', 'επιπεδο'],
    'tags' => ['tags', 'ετικέτες', 'ετικετες', 'keywords', 'λέξεις-κλειδιά', 'λεξεις κλειδια']
];

// Αντιστοίχιση των επικεφαλίδων με τα γνωστά πεδία
foreach ($headers as $index => $header) {
    foreach ($field_mappings as $field => $known_headers) {
        if (in_array($header, $known_headers)) {
            $column_mapping[$field] = $index;
            break;
        }
    }
}

// Έλεγχος για υποχρεωτικά πεδία
if (!isset($column_mapping['question_text'])) {
    $errors[] = "Δεν βρέθηκε αντιστοιχισμένη στήλη για το 'Κείμενο Ερώτησης'. Ελέγξτε τις επικεφαλίδες του CSV.";
    log_debug("Error: No mapped column for 'question_text'. Check CSV headers.");
    fclose($handle);
    return;
}

// Επεξεργασία του CSV με το mapping που δημιουργήσαμε
processCSVWithMapping($file, $column_mapping, $create_missing, $default_category_id, 
                     $default_subcategory_id, $default_chapter_id, $default_question_type, 
                     $delimiter, true); // true για το csv_has_headers

fclose($handle);
}

/**
* Επεξεργασία CSV με προκαθορισμένη μορφή
*/
function processPredefinedCSV($file, $create_missing, $default_category_id, 
                        $default_subcategory_id, $default_chapter_id, 
                        $delimiter, $csv_has_headers) {
// Για την προκαθορισμένη μορφή, έχουμε συγκεκριμένη διάταξη των στηλών:
// 0: Κατηγορία, 1: Υποκατηγορία, 2: Κεφάλαιο, 3: Κείμενο Ερώτησης, 
// 4: Τύπος Ερώτησης, 5: Επεξήγηση, 6: Σωστή Απάντηση, 
// 7-12: Απαντήσεις 1-6, 13: Δυσκολία, 14: Ετικέτες

$column_mapping = [
    'category' => 0,
    'subcategory' => 1,
    'chapter' => 2,
    'question_text' => 3,
    'question_type' => 4,
    'explanation' => 5,
    'correct_answer' => 6,
    'answer1' => 7,
    'answer2' => 8,
    'answer3' => 9,
    'answer4' => 10,
    'answer5' => 11,
    'answer6' => 12,
    'difficulty' => 13,
    'tags' => 14
];

// Καλούμε τη συνάρτηση με το προκαθορισμένο mapping
processCSVWithMapping($file, $column_mapping, $create_missing, $default_category_id, 
                     $default_subcategory_id, $default_chapter_id, 'single_choice', 
                     $delimiter, $csv_has_headers);
}

/**
* Αναλύει το πρώτο μέρος του CSV για να εξάγει τις επικεφαλίδες και ένα δείγμα δεδομένων
*/
function analyzeCSV($file, $delimiter = ';', $sample_lines = 5) {
$result = [
    'headers' => [],
    'sample_data' => []
];

if (!file_exists($file)) {
    return $result;
}

$handle = fopen($file, 'r');
if ($handle === false) {
    return $result;
}

// Ανάγνωση των επικεφαλίδων
$headers = fgetcsv($handle, 0, $delimiter);
if ($headers === false) {
    fclose($handle);
    return $result;
}

// Καθαρισμός επικεφαλίδων
$result['headers'] = array_map(function($header) {
    return clean_encoding($header);
}, $headers);

// Ανάγνωση δείγματος δεδομένων
for ($i = 0; $i < $sample_lines; $i++) {
    $data = fgetcsv($handle, 0, $delimiter);
    if ($data === false) {
        break;
    }
    
    // Καθαρισμός δεδομένων
    $cleaned_data = array_map(function($item) {
        return clean_encoding($item);
    }, $data);
    
    $result['sample_data'][] = $cleaned_data;
}

fclose<?php
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

// Διαθέσιμες επιλογές για τη μαζική εισαγωγή
$import_modes = [
'manual' => 'Χειροκίνητη αντιστοίχιση στηλών',
'auto' => 'Αυτόματη αντιστοίχιση βάσει επικεφαλίδων',
'predefined' => 'Προκαθορισμένη μορφή'
];

// Αρχικοποίηση μεταβλητών αποτελεσμάτων
$success_count = 0;
$error_count = 0;
$errors = [];
$imported_questions = [];
$created_categories = [];
$created_subcategories = [];
$created_chapters = [];
$log_messages = [];

// Επεξεργασία του αιτήματος μαζικής εισαγωγής
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['import_mode'])) {
$import_mode = $_POST['import_mode'];

// Έλεγχος αν έχει υποβληθεί αρχείο CSV
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $error_message = "Δεν επιλέχθηκε έγκυρο αρχείο CSV.";
    log_debug("Error: " . $error_message);
} else {
    $file = $_FILES['csv_file']['tmp_name'];
    
    // Επιλογές για τη μαζική εισαγωγή
    $create_missing = isset($_POST['create_missing']) && $_POST['create_missing'] === 'on';
    $default_category_id = intval($_POST['default_category_id'] ?? 0);
    $default_subcategory_id = intval($_POST['default_subcategory_id'] ?? 0);
    $default_chapter_id = intval($_POST['default_chapter_id'] ?? 0);
    $default_question_type = $_POST['default_question_type'] ?? 'single_choice';
    $delimiter = $_POST['delimiter'] ?? ';';
    $csv_has_headers = isset($_POST['csv_has_headers']) && $_POST['csv_has_headers'] === 'on';
    
    // Επιλογές αντιστοίχισης στηλών
    $column_mapping = [];
    
    if ($import_mode === 'manual') {
        // Χειροκίνητη αντιστοίχιση στηλών
        $mapping_fields = [
            'category', 'subcategory', 'chapter', 'question_text', 'question_type', 
            'explanation', 'correct_answer', 'answer1', 'answer2', 'answer3', 'answer4',
            'answer5', 'answer6', 'difficulty', 'tags'
        ];
        
        foreach ($mapping_fields as $field) {
            if (isset($_POST["map_$field"]) && $_POST["map_$field"] !== '') {
                $column_mapping[$field] = intval($_POST["map_$field"]);
            }
        }
        
        // Έλεγχος υποχρεωτικών πεδίων
        if (!isset($column_mapping['question_text'])) {
            $error_message = "Η αντιστοίχιση του πεδίου 'Κείμενο Ερώτησης' είναι υποχρεωτική.";
            log_debug("Error: " . $error_message);
        } else {
            // Επεξεργασία του CSV με χειροκίνητη αντιστοίχιση
            processCSVWithMapping($file, $column_mapping, $create_missing, $default_category_id, 
                                  $default_subcategory_id, $default_chapter_id, $default_question_type, 
                                  $delimiter, $csv_has_headers);
        }
    } elseif ($import_mode === 'auto') {
        // Αυτόματη αντιστοίχιση βάσει επικεφαλίδων
        processCSVAutoMapping($file, $create_missing, $default_category_id, 
                             $default_subcategory_id, $default_chapter_id, 
                             $default_question_type, $delimiter);
    } elseif ($import_mode === 'predefined') {
        // Προκαθορισμένη μορφή
        processPredefinedCSV($file, $create_missing, $default_category_id, 
                            $default_subcategory_id, $default_chapter_id, 
                            $delimiter, $csv_has_headers);
    }
}
}

/**
* Επεξεργασία CSV με χειροκίνητη αντιστοίχιση στηλών
*/
function processCSVWithMapping($file, $column_mapping, $create_missing, $default_category_id, 
                          $default_subcategory_id, $default_chapter_id, $default_question_type, 
                          $delimiter, $csv_has_headers) {
    global $mysqli, $success_count, $error_count, $errors, $imported_questions, 
           $created_categories, $created_subcategories, $created_chapters, $log_messages;
    
    // Άνοιγμα του αρχείου CSV
    $handle = fopen($file, 'r');
    if ($handle === false) {
        $errors[] = "Αδυναμία ανάγνωσης του αρχείου CSV.";
        log_debug("Error: Unable to read CSV file.");
        return;
    }
    
    // Παράλειψη της γραμμής επικεφαλίδων αν υπάρχει
    if ($csv_has_headers) {
        fgetcsv($handle, 0, $delimiter);
    }
    
    // Επεξεργασία κάθε γραμμής
    $line_number = $csv_has_headers ? 2 : 1;
    
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        try {
            // Εξαγωγή δεδομένων με βάση το mapping
            $question_text = isset($column_mapping['question_text']) ? clean_encoding($data[$column_mapping['question_text']]) : null;
            $category_name = isset($column_mapping['category']) ? clean_encoding($data[$column_mapping['category']]) : null;
            $subcategory_name = isset($column_mapping['subcategory']) ? clean_encoding($data[$column_mapping['subcategory']]) : null;
            $chapter_name = isset($column_mapping['chapter']) ? clean_encoding($data[$column_mapping['chapter']]) : null;
            $question_type = isset($column_mapping['question_type']) ? clean_encoding($data[$column_mapping['question_type']]) : $default_question_type;
            $explanation = isset($column_mapping['explanation']) ? clean_encoding($data[$column_mapping['explanation']]) : '';
            $difficulty = isset($column_mapping['difficulty']) ? intval($data[$column_mapping['difficulty']]) : 1;
            $tags = isset($column_mapping['tags']) ? clean_encoding($data[$column_mapping['tags']]) : '';
            
            // Συνέχιση του κώδικα...
            // ... (όλος ο υπόλοιπος κώδικας μέσα στο while loop)
            
        } catch (Exception $e) {
            $error_count++;
            $errors[] = [
                'line' => $line_number,
                'message' => $e->getMessage(),
                'data' => implode($delimiter, $data)
            ];
            log_debug("Error on line $line_number: " . $e->getMessage());
        }
        
        $line_number++;
    }
    
    fclose($handle);
    return $result;
}