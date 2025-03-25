<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Ανάκτηση των κατηγοριών για τη φόρμα τεστ
$categories_query = "SELECT id, name FROM test_categories ORDER BY name ASC";
$categories_result = $mysqli->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Χειρισμός αιτημάτων POST για δημιουργία/ενημέρωση τεστ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Έλεγχος αν είναι αίτημα διαγραφής
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $test_id = intval($_POST['test_id'] ?? 0);
        if ($test_id > 0) {
            // Διαδικασία διαγραφής
            $delete_query = "DELETE FROM test_generation_questions WHERE test_id = ?";
            $delete_stmt = $mysqli->prepare($delete_query);
            $delete_stmt->bind_param("i", $test_id);
            $delete_stmt->execute();
            
            $delete_query = "DELETE FROM test_generation WHERE id = ?";
            $delete_stmt = $mysqli->prepare($delete_query);
            $delete_stmt->bind_param("i", $test_id);
            
            if ($delete_stmt->execute()) {
                $success = "Το τεστ διαγράφηκε επιτυχώς!";
            } else {
                $error = "Σφάλμα κατά τη διαγραφή του τεστ: " . $delete_stmt->error;
            }
        }
    } 
    // Έλεγχος αν είναι αίτημα δημιουργίας/ενημέρωσης
    else {
        $test_id = isset($_POST['test_id']) ? intval($_POST['test_id']) : 0;
        $category_id = intval($_POST['category_id'] ?? 0);
        $test_name = trim($_POST['test_name'] ?? '');
        $test_label = trim($_POST['test_label'] ?? '');
        $questions_count = intval($_POST['questions_count'] ?? 20);
        $time_limit = intval($_POST['time_limit'] ?? 30);
        $pass_percentage = intval($_POST['pass_percentage'] ?? 70);
        $selection_method = $_POST['selection_method'] ?? 'random';
        
        // Επιπλέον παράμετροι τεστ
        $display_answers_mode = $_POST['display_answers_mode'] ?? 'end_of_test';
        $is_practice = isset($_POST['is_practice']) ? 1 : 0;
        $is_simulation = isset($_POST['is_simulation']) ? 1 : 0;
        $show_explanations = isset($_POST['show_explanations']) ? 1 : 0;
        $show_correct_answers = isset($_POST['show_correct_answers']) ? 1 : 0;
        $randomize_questions = isset($_POST['randomize_questions']) ? 1 : 0;
        $randomize_answers = isset($_POST['randomize_answers']) ? 1 : 0;
        $show_question_numbers = isset($_POST['show_question_numbers']) ? 1 : 0;
        $show_progress_bar = isset($_POST['show_progress_bar']) ? 1 : 0;
        $show_timer = isset($_POST['show_timer']) ? 1 : 0;
        
        // Περιορισμοί
        $max_attempts = intval($_POST['max_attempts'] ?? 0);
        $only_logged_in = isset($_POST['only_logged_in']) ? 1 : 0;
        $required_user_role = $_POST['required_user_role'] ?? '';
        
        // Εμφάνιση
        $primary_color = $_POST['primary_color'] ?? '#aa3636';
        $background_color = $_POST['background_color'] ?? '#f5f5f5';
        
        // Προγραμματισμός 
        $is_scheduled = isset($_POST['is_scheduled']) ? 1 : 0;
        $schedule_date = null;
        if ($is_scheduled && !empty($_POST['schedule_date'])) {
            $schedule_date = $_POST['schedule_date'];
        }
        
        // Επεξεργασία της κατανομής κεφαλαίων (αν υπάρχει)
        $chapter_distribution = isset($_POST['chapter_distribution']) ? json_encode($_POST['chapter_distribution']) : '{}';
        
        // Κατάσταση του τεστ
        $status = $is_scheduled ? 'scheduled' : 'active';
        
        // Έλεγχος εγκυρότητας
        if (empty($test_name) || $category_id === 0 || $questions_count <= 0) {
            $error = "Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία";
        } else {
            if ($test_id > 0) {
                // Ενημέρωση υπάρχοντος τεστ
                $update_query = "UPDATE test_generation SET 
                                test_name = ?, 
                                label = ?,
                                category_id = ?,
                                questions_count = ?, 
                                time_limit = ?, 
                                pass_percentage = ?, 
                                selection_method = ?, 
                                chapter_distribution = ?,
                                display_answers_mode = ?,
                                is_practice = ?,
                                is_simulation = ?,
                                show_explanations = ?,
                                show_correct_answers = ?,
                                randomize_questions = ?,
                                randomize_answers = ?,
                                show_question_numbers = ?,
                                show_progress_bar = ?,
                                show_timer = ?,
                                max_attempts = ?,
                                required_user_role = ?,
                                primary_color = ?,
                                background_color = ?,
                                schedule_date = ?,
                                status = ?,
                                updated_at = NOW()
                                WHERE id = ?";
                
                $stmt = $mysqli->prepare($update_query);
                $stmt->bind_param("ssiissssiiiiiiiiiissssssi", 
                    $test_name, $test_label, $category_id, $questions_count, $time_limit, 
                    $pass_percentage, $selection_method, $chapter_distribution, $display_answers_mode, 
                    $is_practice, $is_simulation, $show_explanations, $show_correct_answers,
                    $randomize_questions, $randomize_answers, $show_question_numbers, 
                    $show_progress_bar, $show_timer, $max_attempts, $required_user_role,
                    $primary_color, $background_color, $schedule_date, $status, $test_id);
                
                if ($stmt->execute()) {
                    $success = "Το τεστ ενημερώθηκε επιτυχώς!";
                } else {
                    $error = "Σφάλμα κατά την ενημέρωση: " . $stmt->error;
                }
            } else {
                // Δημιουργία νέου τεστ
                $created_by = $_SESSION['user_id'];
                
                $insert_query = "INSERT INTO test_generation (
                                test_name, label, category_id, questions_count, time_limit,
                                pass_percentage, selection_method, chapter_distribution, 
                                display_answers_mode, is_practice, is_simulation, show_explanations,
                                show_correct_answers, randomize_questions, randomize_answers,
                                show_question_numbers, show_progress_bar, show_timer,
                                max_attempts, required_user_role, primary_color, background_color,
                                schedule_date, status, created_by, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $mysqli->prepare($insert_query);
                $stmt->bind_param("ssiiisssiiiiiiiiiiisssssi", 
                    $test_name, $test_label, $category_id, $questions_count, $time_limit, 
                    $pass_percentage, $selection_method, $chapter_distribution, $display_answers_mode, 
                    $is_practice, $is_simulation, $show_explanations, $show_correct_answers,
                    $randomize_questions, $randomize_answers, $show_question_numbers, 
                    $show_progress_bar, $show_timer, $max_attempts, $required_user_role,
                    $primary_color, $background_color, $schedule_date, $status, $created_by);
                
                if ($stmt->execute()) {
                    $test_id = $stmt->insert_id;
                    
                    // Επιλογή ερωτήσεων βάσει παραμέτρων
                    $questions = selectQuestionsForTest($mysqli, [
                        'category_id' => $category_id,
                        'questions_count' => $questions_count,
                        'selection_method' => $selection_method,
                        'chapter_distribution' => $chapter_distribution
                    ]);
                    
                    if (empty($questions)) {
                        // Αν δεν βρέθηκαν ερωτήσεις, διαγράφουμε το τεστ
                        $delete_query = "DELETE FROM test_generation WHERE id = ?";
                        $delete_stmt = $mysqli->prepare($delete_query);
                        $delete_stmt->bind_param("i", $test_id);
                        $delete_stmt->execute();
                        
                        $error = "Δεν βρέθηκαν ερωτήσεις για τις επιλεγμένες παραμέτρους.";
                    } else {
                        // Αποθήκευση των ερωτήσεων στο τεστ
                        foreach ($questions as $index => $question_id) {
                            $position = $index + 1;
                            $question_query = "INSERT INTO test_generation_questions (test_id, question_id, position) VALUES (?, ?, ?)";
                            $question_stmt = $mysqli->prepare($question_query);
                            $question_stmt->bind_param("iii", $test_id, $question_id, $position);
                            $question_stmt->execute();
                        }
                        
                        $success = "Το τεστ δημιουργήθηκε επιτυχώς!";
                    }
                } else {
                    $error = "Σφάλμα κατά τη δημιουργία: " . $stmt->error;
                }
            }
        }
    }
}

// Ανάκτηση δεδομένων για επεξεργασία (αν έχει επιλεγεί κάποιο τεστ)
$editing_test = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $edit_id = intval($_GET['edit']);
    $edit_query = "SELECT * FROM test_generation WHERE id = ?";
    $edit_stmt = $mysqli->prepare($edit_query);
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $editing_test = $edit_stmt->get_result()->fetch_assoc();
}

// Ανάκτηση όλων των υπαρχόντων τεστ
$tests_query = "SELECT tg.id, tg.test_name, tg.label, tg.created_at, tg.questions_count, 
               tg.is_practice, tg.is_simulation, tg.status,
               tc.name as category_name, u.fullname as creator_name
               FROM test_generation tg
               JOIN test_configurations cf ON tg.config_id = cf.id
               JOIN test_categories tc ON cf.category_id = tc.id
               JOIN users u ON tg.created_by = u.id
               ORDER BY tg.created_at DESC";

$tests_result = $mysqli->query($tests_query);
$tests = $tests_result ? $tests_result->fetch_all(MYSQLI_ASSOC) : [];

// Φόρτωση ρόλων χρηστών
$roles_query = "SELECT DISTINCT role FROM users WHERE role IS NOT NULL ORDER BY role";
$roles_result = $mysqli->query($roles_query);
$user_roles = $roles_result ? $roles_result->fetch_all(MYSQLI_ASSOC) : [];

// Συνάρτηση για την επιλογή ερωτήσεων βάσει παραμέτρων
function selectQuestionsForTest($mysqli, $params) {
    $category_id = $params['category_id'];
    $questions_count = $params['questions_count'];
    $selection_method = $params['selection_method'];
    $chapter_distribution = json_decode($params['chapter_distribution'] ?? '{}', true);
    
    $selected_questions = [];
    
    // Ανάλογα με τη μέθοδο επιλογής
    switch ($selection_method) {
        case 'random':
            // Τυχαία επιλογή ερωτήσεων
            $query = "SELECT q.id FROM questions q
                     JOIN test_chapters c ON q.chapter_id = c.id
                     JOIN test_subcategories s ON c.subcategory_id = s.id
                     WHERE s.test_category_id = ? AND q.status = 'active'
                     ORDER BY RAND() LIMIT ?";
            
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", $category_id, $questions_count);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $selected_questions[] = $row['id'];
            }
            break;
            
        case 'proportional':
        case 'fixed':
            // Αναλογική/σταθερή επιλογή από κεφάλαια
            if (empty($chapter_distribution)) return [];
            
            foreach ($chapter_distribution as $chapter_id => $value) {
                if ($value <= 0) continue;
                
                $chapter_count = $selection_method === 'proportional' 
                    ? round(($value / 100) * $questions_count) 
                    : intval($value);
                
                if ($chapter_count <= 0) continue;
                
                $query = "SELECT id FROM questions WHERE chapter_id = ? AND status = 'active' ORDER BY RAND() LIMIT ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("ii", $chapter_id, $chapter_count);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $selected_questions[] = $row['id'];
                }
            }
            break;
    }
    
    // Ανακάτεμα και περιορισμός σε $questions_count ερωτήσεις
    shuffle($selected_questions);
    if (count($selected_questions) > $questions_count) {
        $selected_questions = array_slice($selected_questions, 0, $questions_count);
    }
    
    return $selected_questions;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Τεστ</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/tabbed_interface.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/quizzes.css">
    <style>
        /* Επιπλέον inline στυλ που μπορεί να χρειαστούν */
        .tabs-container {
            margin-bottom: 25px;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .test-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .test-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .test-card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .test-card-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: var(--primary-color);
        }
        
        .test-card-subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .test-card-body {
            margin-bottom: 15px;
        }
        
        .test-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .test-info-label {
            color: #666;
        }
        
        .test-info-value {
            font-weight: 500;
        }
        
        .test-card-footer {
            display: flex;
            gap: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            color: white;
        }
        
        .practice-badge { background-color: #28a745; }
        .simulation-badge { background-color: #007bff; }
        .normal-badge { background-color: #6c757d; }
        .active-badge { background-color: #28a745; }
        .scheduled-badge { background-color: #fd7e14; }
        .inactive-badge { background-color: #6c757d; }
        
        /* Κουμπιά ενεργειών */
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-edit { background-color: #007bff; }
        .btn-view { background-color: #6c757d; }
        .btn-duplicate { background-color: #17a2b8; }
        .btn-delete { background-color: #dc3545; }
        
        .btn-edit:hover { background-color: #0069d9; }
        .btn-view:hover { background-color: #5a6268; }
        .btn-duplicate:hover { background-color: #138496; }
        .btn-delete:hover { background-color: #c82333; }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .test-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    
    <main class="admin-container">
        <h1 class="admin-title">📋 Διαχείριση Τεστ</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <!-- Tab navigation -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <div class="tab-button <?= !isset($_GET['edit']) ? 'active' : '' ?>" data-tab="list">Λίστα Τεστ</div>
                <div class="tab-button <?= isset($_GET['edit']) ? 'active' : '' ?>" data-tab="create">
                    <?= isset($_GET['edit']) ? 'Επεξεργασία Τεστ' : 'Δημιουργία Νέου Τεστ' ?>
                </div>
            </div>
            
            <!-- Tab: Λίστα Τεστ -->
            <div class="tab-content <?= !isset($_GET['edit']) ? 'active' : '' ?>" id="list-tab">
                <div class="tab-header">
                    <div class="tab-title">Υπάρχοντα Τεστ</div>
                    <div class="tab-actions">
                        <button class="btn-primary" onclick="showTab('create')">➕ Νέο Τεστ</button>
                        <div class="filter-container">
                            <input type="text" id="test-search" placeholder="Αναζήτηση..." class="search-input">
                            <select id="status-filter" class="filter-select">
                                <option value="">Όλες οι καταστάσεις</option>
                                <option value="active">Ενεργά</option>
                                <option value="scheduled">Προγραμματισμένα</option>
                                <option value="inactive">Ανενεργά</option>
                            </select>
                            <button id="clear-filters" class="btn-secondary">🔄 Καθαρισμός</button>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($tests)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <h3>Δεν βρέθηκαν τεστ</h3>
                        <p>Δεν υπάρχουν ακόμα τεστ. Κάντε κλικ στο "Νέο Τεστ" για να δημιουργήσετε το πρώτο σας τεστ.</p>
                        <button class="btn-primary" onclick="showTab('create')">➕ Δημιουργία Πρώτου Τεστ</button>
                    </div>
                <?php else: ?>
                    <div class="test-grid">
                        <?php foreach ($tests as $test): ?>
                            <div class="test-card" data-status="<?= $test['status'] ?>">
                                <div class="test-card-header">
                                    <h3 class="test-card-title"><?= htmlspecialchars($test['test_name']) ?></h3>
                                    <div class="test-card-subtitle">
                                        <?= htmlspecialchars($test['category_name']) ?>
                                        
                                        <?php if ($test['is_practice']): ?>
                                            <span class="badge practice-badge">Εξάσκηση</span>
                                        <?php elseif ($test['is_simulation']): ?>
                                            <span class="badge simulation-badge">Προσομοίωση</span>
                                        <?php else: ?>
                                            <span class="badge normal-badge">Κανονικό</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($test['status'] === 'active'): ?>
                                            <span class="badge active-badge">Ενεργό</span>
                                        <?php elseif ($test['status'] === 'scheduled'): ?>
                                            <span class="badge scheduled-badge">Προγραμματισμένο</span>
                                        <?php else: ?>
                                            <span class="badge inactive-badge">Ανενεργό</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="test-card-body">
                                    <?php if (!empty($test['label'])): ?>
                                        <div class="test-label"><?= htmlspecialchars($test['label']) ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="test-info">
                                        <span class="test-info-label">Ερωτήσεις:</span>
                                        <span class="test-info-value"><?= $test['questions_count'] ?></span>
                                    </div>
                                    
                                    <div class="test-info">
                                        <span class="test-info-label">Δημιουργήθηκε:</span>
                                        <span class="test-info-value"><?= date('d/m/Y', strtotime($test['created_at'])) ?></span>
                                    </div>
                                    
                                    <div class="test-info">
                                        <span class="test-info-label">Δημιουργός:</span>
                                        <span class="test-info-value"><?= htmlspecialchars($test['creator_name']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="test-card-footer">
                                    <a href="?edit=<?= $test['id'] ?>" class="btn-icon btn-edit" title="Επεξεργασία">✏️</a>
                                    <a href="view_test.php?id=<?= $test['id'] ?>" class="btn-icon btn-view" title="Προβολή">👁️</a>
                                    <a href="view_results.php?test_id=<?= $test['id'] ?>" class="btn-icon btn-view" title="Αποτελέσματα">📊</a>
                                    <a href="duplicate_test.php?id=<?= $test['id'] ?>" class="btn-icon btn-duplicate" title="Αντιγραφή">🔄</a>
                                    <form method="post" onsubmit="return confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το τεστ;');" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                                        <button type="submit" class="btn-icon btn-delete" title="Διαγραφή">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Δημιουργία/Επεξεργασία Τεστ -->
            <div class="tab-content <?= isset($_GET['edit']) ? 'active' : '' ?>" id="create-tab">
                <div class="tab-header">
                    <div class="tab-title">
                        <?= $editing_test ? 'Επεξεργασία: ' . htmlspecialchars($editing_test['test_name']) : 'Δημιουργία Νέου Τεστ' ?>
                    </div>
                    <div class="tab-actions">
                        <?php if ($editing_test): ?>
                            <a href="view_test.php?id=<?= $editing_test['id'] ?>" class="btn-secondary">👁️ Προβολή Τεστ</a>
                        <?php endif; ?>
                        <button type="button" class="btn-secondary" onclick="showTab('list')">↩️ Επιστροφή στη Λίστα</button>
                    </div>
                </div>
                
                <form method="POST" class="admin-form" id="test-form">
                    <?php if ($editing_test): ?>
                        <input type="hidden" name="test_id" value="<?= $editing_test['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-tabs">
                        <div class="form-tab-nav">
                            <div class="form-tab-button active" data-formtab="basic">Βασικές Πληροφορίες</div>
                            <div class="form-tab-button" data-formtab="appearance">Εμφάνιση</div>
                            <div class="form-tab-button" data-formtab="behavior">Συμπεριφορά</div>
                            <div class="form-tab-button" data-formtab="restrictions">Περιορισμοί</div>
                            <div class="form-tab-button" data-formtab="schedule">Προγραμματισμός</div>
                        </div>
                        
                 <!-- Tab: Βασικές Πληροφορίες -->
                 <div class="form-tab-content active" id="basic-formtab">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category_id">Κατηγορία:</label>
                                    <select id="category_id" name="category_id" required>
                                        <option value="">-- Επιλέξτε Κατηγορία --</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>" <?= ($editing_test && $editing_test['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="test_name">Όνομα Τεστ:</label>
                                    <input type="text" id="test_name" name="test_name" required value="<?= $editing_test ? htmlspecialchars($editing_test['test_name']) : '' ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="test_label">Ετικέτα/Περιγραφή (προαιρετικό):</label>
                                <input type="text" id="test_label" name="test_label" value="<?= $editing_test ? htmlspecialchars($editing_test['label']) : '' ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="questions_count">Αριθμός Ερωτήσεων:</label>
                                    <input type="number" id="questions_count" name="questions_count" value="<?= $editing_test ? $editing_test['questions_count'] : '20' ?>" min="5" max="100" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="time_limit">Χρονικό Όριο (λεπτά):</label>
                                    <input type="number" id="time_limit" name="time_limit" value="<?= $editing_test ? $editing_test['time_limit'] : '30' ?>" min="0" max="120">
                                    <div class="help-text">Εισάγετε 0 για απεριόριστο χρόνο</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="pass_percentage">Ποσοστό Επιτυχίας (%):</label>
                                    <input type="number" id="pass_percentage" name="pass_percentage" value="<?= $editing_test ? $editing_test['pass_percentage'] : '70' ?>" min="50" max="100" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="selection_method">Μέθοδος Επιλογής Ερωτήσεων:</label>
                                <select id="selection_method" name="selection_method" required>
                                    <option value="random" <?= ($editing_test && $editing_test['selection_method'] == 'random') ? 'selected' : '' ?>>Τυχαία</option>
                                    <option value="proportional" <?= ($editing_test && $editing_test['selection_method'] == 'proportional') ? 'selected' : '' ?>>Αναλογική βάσει κεφαλαίων</option>
                                    <option value="fixed" <?= ($editing_test && $editing_test['selection_method'] == 'fixed') ? 'selected' : '' ?>>Συγκεκριμένος αριθμός ανά κεφάλαιο</option>
                                </select>
                            </div>
                            
                            <div id="chapter_distribution_container" style="display: <?= ($editing_test && ($editing_test['selection_method'] == 'proportional' || $editing_test['selection_method'] == 'fixed')) ? 'block' : 'none' ?>;">
                                <h3>Κατανομή Ερωτήσεων ανά Κεφάλαιο</h3>
                                <div id="chapters_list">
                                    <!-- Θα συμπληρωθεί μέσω JavaScript -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tab: Εμφάνιση -->
                        <div class="form-tab-content" id="appearance-formtab">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="primary_color">Κύριο Χρώμα:</label>
                                    <div class="color-selector">
                                        <input type="color" id="primary_color" name="primary_color" value="<?= $editing_test ? $editing_test['primary_color'] : '#aa3636' ?>">
                                        <span class="color-value"><?= $editing_test ? $editing_test['primary_color'] : '#aa3636' ?></span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="background_color">Χρώμα Φόντου:</label>
                                    <div class="color-selector">
                                        <input type="color" id="background_color" name="background_color" value="<?= $editing_test ? $editing_test['background_color'] : '#f5f5f5' ?>">
                                        <span class="color-value"><?= $editing_test ? $editing_test['background_color'] : '#f5f5f5' ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Εμφάνιση Στοιχείων:</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="show_question_numbers" name="show_question_numbers" value="1" <?= (!$editing_test || $editing_test['show_question_numbers']) ? 'checked' : '' ?>>
                                        <label for="show_question_numbers">Εμφάνιση αρίθμησης ερωτήσεων</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="show_progress_bar" name="show_progress_bar" value="1" <?= (!$editing_test || $editing_test['show_progress_bar']) ? 'checked' : '' ?>>
                                        <label for="show_progress_bar">Εμφάνιση μπάρας προόδου</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="show_timer" name="show_timer" value="1" <?= (!$editing_test || $editing_test['show_timer']) ? 'checked' : '' ?>>
                                        <label for="show_timer">Εμφάνιση χρονομέτρου</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tab: Συμπεριφορά -->
                        <div class="form-tab-content" id="behavior-formtab">
                            <div class="form-group">
                                <label>Τύπος Τεστ:</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="is_practice" name="is_practice" value="1" <?= ($editing_test && $editing_test['is_practice']) ? 'checked' : '' ?>>
                                        <label for="is_practice">Τεστ Εξάσκησης</label>
                                        <div class="help-text">Οι χρήστες λαμβάνουν άμεση ανατροφοδότηση μετά από κάθε απάντηση</div>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="is_simulation" name="is_simulation" value="1" <?= ($editing_test && $editing_test['is_simulation']) ? 'checked' : '' ?>>
                                        <label for="is_simulation">Τεστ Προσομοίωσης</label>
                                        <div class="help-text">Προσομοίωση πραγματικών συνθηκών εξέτασης</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="display_answers_mode">Εμφάνιση Απαντήσεων:</label>
                                    <select id="display_answers_mode" name="display_answers_mode">
                                        <option value="end_of_test" <?= ($editing_test && $editing_test['display_answers_mode'] == 'end_of_test') ? 'selected' : '' ?>>Στο τέλος του τεστ</option>
                                        <option value="after_each_question" <?= ($editing_test && $editing_test['display_answers_mode'] == 'after_each_question') ? 'selected' : '' ?>>Μετά από κάθε ερώτηση</option>
                                        <option value="never" <?= ($editing_test && $editing_test['display_answers_mode'] == 'never') ? 'selected' : '' ?>>Ποτέ</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="show_explanations">Εμφάνιση Επεξηγήσεων:</label>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="show_explanations" name="show_explanations" value="1" <?= (!$editing_test || $editing_test['show_explanations']) ? 'checked' : '' ?>>
                                        <label for="show_explanations" class="toggle-label"></label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="show_correct_answers">Εμφάνιση Σωστών Απαντήσεων:</label>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="show_correct_answers" name="show_correct_answers" value="1" <?= (!$editing_test || $editing_test['show_correct_answers']) ? 'checked' : '' ?>>
                                        <label for="show_correct_answers" class="toggle-label"></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="randomize_questions">Τυχαία Σειρά Ερωτήσεων:</label>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="randomize_questions" name="randomize_questions" value="1" <?= (!$editing_test || $editing_test['randomize_questions']) ? 'checked' : '' ?>>
                                        <label for="randomize_questions" class="toggle-label"></label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="randomize_answers">Τυχαία Σειρά Απαντήσεων:</label>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="randomize_answers" name="randomize_answers" value="1" <?= (!$editing_test || $editing_test['randomize_answers']) ? 'checked' : '' ?>>
                                        <label for="randomize_answers" class="toggle-label"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tab: Περιορισμοί -->
                        <div class="form-tab-content" id="restrictions-formtab">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_attempts">Μέγιστος Αριθμός Προσπαθειών:</label>
                                    <input type="number" id="max_attempts" name="max_attempts" value="<?= $editing_test ? $editing_test['max_attempts'] : '0' ?>" min="0">
                                    <div class="help-text">0 = Απεριόριστες προσπάθειες</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="only_logged_in">Μόνο για Συνδεδεμένους Χρήστες:</label>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="only_logged_in" name="only_logged_in" value="1" <?= ($editing_test && $editing_test['only_logged_in']) ? 'checked' : '' ?>>
                                        <label for="only_logged_in" class="toggle-label"></label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="required_user_role">Απαιτούμενος Ρόλος Χρήστη:</label>
                                    <select id="required_user_role" name="required_user_role">
                                        <option value="">Όλοι οι ρόλοι</option>
                                        <?php foreach ($user_roles as $role): ?>
                                            <option value="<?= htmlspecialchars($role['role']) ?>" <?= ($editing_test && $editing_test['required_user_role'] == $role['role']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($role['role']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tab: Προγραμματισμός -->
                        <div class="form-tab-content" id="schedule-formtab">
                            <div class="form-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="is_scheduled" name="is_scheduled" value="1" <?= ($editing_test && $editing_test['schedule_date'] != null) ? 'checked' : '' ?>>
                                    <label for="is_scheduled">Προγραμματισμένο τεστ</label>
                                </div>
                                <div id="schedule_date_container" style="<?= ($editing_test && $editing_test['schedule_date'] != null) ? 'display: block;' : 'display: none;' ?> margin-top: 10px;">
                                    <label for="schedule_date">Ημερομηνία και ώρα διεξαγωγής:</label>
                                    <input type="datetime-local" id="schedule_date" name="schedule_date" value="<?= ($editing_test && $editing_test['schedule_date'] != null) ? date('Y-m-d\TH:i', strtotime($editing_test['schedule_date'])) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><?= $editing_test ? '💾 Ενημέρωση Τεστ' : '🔰 Δημιουργία Τεστ' ?></button>
                        <button type="button" class="btn-secondary" onclick="showTab('list')">↩️ Ακύρωση</button>
                        
                        <?php if ($editing_test): ?>
                            <a href="view_test.php?id=<?= $editing_test['id'] ?>" class="btn-secondary">👁️ Προβολή Τεστ</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab navigation
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    showTab(tabName);
                });
            });
            
            // Form tab navigation
            const formTabButtons = document.querySelectorAll('.form-tab-button');
            const formTabContents = document.querySelectorAll('.form-tab-content');
            
            formTabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    formTabButtons.forEach(btn => btn.classList.remove('active'));
                    formTabContents.forEach(content => content.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(this.getAttribute('data-formtab') + '-formtab').classList.add('active');
                });
            });
            
            // Selection method change
            const selectionMethod = document.getElementById('selection_method');
            const chapterDistributionContainer = document.getElementById('chapter_distribution_container');
            
            if (selectionMethod && chapterDistributionContainer) {
                selectionMethod.addEventListener('change', function() {
                    if (this.value === 'proportional' || this.value === 'fixed') {
                        chapterDistributionContainer.style.display = 'block';
                        loadChapters();
                    } else {
                        chapterDistributionContainer.style.display = 'none';
                    }
                });
            }
            
            // Category change - load chapters
            const categorySelect = document.getElementById('category_id');
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    if (selectionMethod.value === 'proportional' || selectionMethod.value === 'fixed') {
                        loadChapters();
                    }
                });
            }
            
            // Color picker value display
            const colorInputs = document.querySelectorAll('input[type="color"]');
            colorInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const valueDisplay = this.parentElement.querySelector('.color-value');
                    if (valueDisplay) {
                        valueDisplay.textContent = this.value;
                    }
                });
            });
            
            // Schedule checkbox
            const isScheduledCheckbox = document.getElementById('is_scheduled');
            const scheduleDateContainer = document.getElementById('schedule_date_container');
            
            if (isScheduledCheckbox && scheduleDateContainer) {
                isScheduledCheckbox.addEventListener('change', function() {
                    scheduleDateContainer.style.display = this.checked ? 'block' : 'none';
                });
            }
            
            // Φιλτράρισμα τεστ
            const testSearch = document.getElementById('test-search');
            const statusFilter = document.getElementById('status-filter');
            const clearFiltersBtn = document.getElementById('clear-filters');
            
            function filterTests() {
                const searchText = testSearch.value.toLowerCase();
                const statusValue = statusFilter.value;
                
                document.querySelectorAll('.test-card').forEach(card => {
                    const title = card.querySelector('.test-card-title').textContent.toLowerCase();
                    const subtitle = card.querySelector('.test-card-subtitle').textContent.toLowerCase();
                    const status = card.getAttribute('data-status');
                    
                    const matchesSearch = !searchText || title.includes(searchText) || subtitle.includes(searchText);
                    const matchesStatus = !statusValue || status === statusValue;
                    
                    card.style.display = (matchesSearch && matchesStatus) ? 'block' : 'none';
                });
            }
            
            if (testSearch && statusFilter && clearFiltersBtn) {
                testSearch.addEventListener('input', filterTests);
                statusFilter.addEventListener('change', filterTests);
                
                clearFiltersBtn.addEventListener('click', function() {
                    testSearch.value = '';
                    statusFilter.value = '';
                    filterTests();
                });
            }
            
            // Φόρτωση κεφαλαίων από τη βάση δεδομένων
            function loadChapters() {
                const categoryId = categorySelect.value;
                const chaptersList = document.getElementById('chapters_list');
                
                if (!categoryId || !chaptersList) return;
                
                chaptersList.innerHTML = '<p class="loading">Φόρτωση κεφαλαίων...</p>';
                
                fetch(`get_chapters_for_category.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderChapters(data.chapters);
                        } else {
                            chaptersList.innerHTML = `<p class="error">${data.message || 'Σφάλμα φόρτωσης κεφαλαίων'}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error loading chapters:', error);
                        chaptersList.innerHTML = '<p class="error">Σφάλμα φόρτωσης κεφαλαίων. Παρακαλώ δοκιμάστε ξανά.</p>';
                    });
            }
            
            // Απεικόνιση λίστας κεφαλαίων
            function renderChapters(chapters) {
                const chaptersList = document.getElementById('chapters_list');
                const isProportional = selectionMethod.value === 'proportional';
                
                if (chapters.length === 0) {
                    chaptersList.innerHTML = '<p>Δεν βρέθηκαν κεφάλαια για την επιλεγμένη κατηγορία.</p>';
                    return;
                }
                
                let html = '';
                
                chapters.forEach(chapter => {
                    html += `
                        <div class="chapter-item">
                            <div class="chapter-info">
                                <label>${escapeHtml(chapter.name)}</label>
                                ${chapter.description ? `<span class="chapter-description">${escapeHtml(chapter.description)}</span>` : ''}
                            </div>
                            <div class="chapter-input">
                                <input type="number" name="chapter_distribution[${chapter.id}]" min="0" value="0" 
                                    ${isProportional ? 'max="100"' : ''}>
                                <span class="unit">${isProportional ? '%' : 'ερωτήσεις'}</span>
                            </div>
                        </div>
                    `;
                });
                
                chaptersList.innerHTML = html;
                
                if (isProportional) {
                    const inputs = chaptersList.querySelectorAll('input[type="number"]');
                    inputs.forEach(input => {
                        input.addEventListener('change', function() {
                            validateTotalPercentage(inputs);
                        });
                    });
                }
            }
            
            // Επαλήθευση συνολικού ποσοστού (για αναλογική κατανομή)
            function validateTotalPercentage(inputs) {
                let total = 0;
                inputs.forEach(input => {
                    total += parseInt(input.value) || 0;
                });
                
                const warningElement = document.getElementById('percentage-warning') || document.createElement('div');
                warningElement.id = 'percentage-warning';
                warningElement.className = total === 100 ? 'alert alert-success' : 'alert alert-warning';
                warningElement.textContent = total === 100 
                    ? 'Το συνολικό ποσοστό είναι 100%. ✓' 
                    : `Προσοχή: Το συνολικό ποσοστό είναι ${total}%. Θα πρέπει να είναι ακριβώς 100%.`;
                
                const container = document.getElementById('chapter_distribution_container');
                
                if (!document.getElementById('percentage-warning') && container) {
                    container.appendChild(warningElement);
                }
            }
            
            // Ασφαλής εμφάνιση κειμένου (αποφυγή XSS)
            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }
            
            // Προεπιλεγμένες τιμές αν επεξεργαζόμαστε υπάρχον τεστ
            <?php if ($editing_test && ($editing_test['selection_method'] == 'proportional' || $editing_test['selection_method'] == 'fixed')): ?>
                loadChapters();
            <?php endif; ?>
        });
        
        // Εναλλαγή μεταξύ tabs
        function showTab(tabName) {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(btn => {
                if (btn.getAttribute('data-tab') === tabName) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            tabContents.forEach(content => {
                if (content.id === tabName + '-tab') {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>