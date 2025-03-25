<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Μήνυμα επιτυχίας αν υπάρχει
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = "✅ Η ερώτηση προστέθηκε επιτυχώς!";
            break;
        case 'updated':
            $success_message = "✅ Η ερώτηση ενημερώθηκε επιτυχώς!";
            break;
        case 'deleted':
            $success_message = "✅ Η ερώτηση διαγράφηκε επιτυχώς!";
            break;
    }
}

// Μήνυμα λάθους αν υπάρχει
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'not_found':
            $error_message = "❌ Η ερώτηση δεν βρέθηκε!";
            break;
        case 'permission':
            $error_message = "❌ Δεν έχετε δικαιώματα για αυτή την ενέργεια!";
            break;
        default:
            $error_message = "❌ Προέκυψε ένα σφάλμα!";
    }
}
?>


<head>
    <link rel="stylesheet" href="../assets/css/question_manager.css">
</head>

<main class="admin-container">
    <h2 class="admin-title">📌 Διαχείριση Ερωτήσεων</h2>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success">
    <?= $success_message ?>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert alert-error">
    <?= $error_message ?>
</div>
<?php endif; ?>
    <div id="question-list-container">


<!-- Προσθέστε αυτό μετά τον τίτλο "Διαχείριση Ερωτήσεων" και πριν τη μπάρα μαζικών ενεργειών -->
<div class="filters-container">
    <div class="filters-header">
        <h3>📋 Φίλτρα Ερωτήσεων</h3>
        <button type="button" id="toggle-filters-btn" class="btn-secondary">
            <span class="expand-icon">▼</span><span class="collapse-icon" style="display:none;">▲</span> 
            Φίλτρα
        </button>
    </div>
    
    <div id="filters-panel" style="display:none;">
        <div class="filters-row">
            <div class="filter-group">
                <label for="filter-category">Κατηγορία:</label>
                <select id="filter-category" class="filter-select">
                    <option value="">Όλες οι κατηγορίες</option>
                    <!-- Θα συμπληρωθεί δυναμικά -->
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-subcategory">Υποκατηγορία:</label>
                <select id="filter-subcategory" class="filter-select">
                    <option value="">Όλες οι υποκατηγορίες</option>
                    <!-- Θα συμπληρωθεί δυναμικά -->
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-chapter">Κεφάλαιο:</label>
                <select id="filter-chapter" class="filter-select">
                    <option value="">Όλα τα κεφάλαια</option>
                    <!-- Θα συμπληρωθεί δυναμικά -->
                </select>
            </div>
        </div>
        
        <div class="filters-row">
            <div class="filter-group">
                <label for="filter-type">Τύπος Ερώτησης:</label>
                <select id="filter-type" class="filter-select">
                    <option value="">Όλοι οι τύποι</option>
                    <option value="single_choice">Μονής Επιλογής</option>
                    <option value="multiple_choice">Πολλαπλής Επιλογής</option>
                    <option value="true_false">Σωστό/Λάθος</option>
                    <option value="fill_in_blank">Συμπλήρωση Κενού</option>
                    <option value="matching">Αντιστοίχιση</option>
                    <option value="ordering">Ταξινόμηση</option>
                    <option value="short_answer">Σύντομη Απάντηση</option>
                    <option value="essay">Ανάπτυξη</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-status">Κατάσταση:</label>
                <select id="filter-status" class="filter-select">
                    <option value="">Όλες οι καταστάσεις</option>
                    <option value="active">Ενεργή</option>
                    <option value="inactive">Ανενεργή</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-search">Αναζήτηση:</label>
                <input type="text" id="filter-search" class="filter-input" placeholder="Αναζήτηση στις ερωτήσεις...">
            </div>
        </div>
        
        <div class="filters-actions">
            <button type="button" id="apply-filters-btn" class="btn-primary">🔍 Εφαρμογή Φίλτρων</button>
            <button type="button" id="reset-filters-btn" class="btn-secondary">↻ Επαναφορά</button>
            <div class="filters-info">
                <span>Εμφάνιση <strong id="filtered-count">0</strong> από <strong id="total-count">0</strong> ερωτήσεις</span>
            </div>
        </div>
    </div>
</div>


        <!-- Μπάρα μαζικών ενεργειών -->
        <div id="bulk-actions-bar" style="display: none;">
            <div class="bulk-selection-info">
                <span>Επιλεγμένες ερωτήσεις: <strong id="selected-count">0</strong></span>
            </div>
            <div class="bulk-actions">
                <button type="button" class="btn-danger" onclick="bulkDeleteQuestions()">🗑️ Μαζική Διαγραφή</button>
                <button type="button" class="btn-secondary" onclick="selectAllQuestions(false)">❌ Καθαρισμός Επιλογών</button>
            </div>
        </div>

        <table id="questions-table" class="admin-table resizable-table">
    <thead>
        <tr>
            <th style="width: 30px;">
                <input type="checkbox" id="select-all-questions" title="Επιλογή όλων">
            </th>
            <th style="width: 35%;">Ερώτηση</th>
            <th style="width: 15%;">Κατηγορία</th>
            <th style="width: 15%;">Υποκατηγορία</th>
            <th style="width: 15%;">Κεφάλαιο</th>
            <th style="width: 10%;">Κατάσταση</th>
            <th style="width: 50px;">ID</th>
            <th style="width: 80px;">Ενέργειες</th>
        </tr>
    </thead>
    <tbody id="questions-table-body"></tbody>
</table>

        <button id="add-question-btn" class="btn-primary">➕ Προσθήκη Ερώτησης</button>
    </div>

    <div id="question-form-container" style="display: none;" role="dialog" aria-label="Φόρμα Επεξεργασίας Ερώτησης">
        <button type="button" id="back-to-list-btn" class="btn-secondary">↩️ Επιστροφή στη λίστα</button>

        <form id="question-form" enctype="multipart/form-data" class="admin-form">
            <div class="select-group">
                <div class="form-group">
                    <label for="subcategory-select" class="sr-only">Υποκατηγορία:</label>
                    <select id="subcategory-select" required aria-required="true">
                        <option value="">-- Επιλέξτε Υποκατηγορία --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="chapter-select" class="sr-only">Κεφάλαιο:</label>
                    <select id="chapter-select" required aria-required="true">
                        <option value="">-- Επιλέξτε Κεφάλαιο --</option>
                    </select>
                </div>
            </div>

            <div class="form-group question-id-container">
                <label>Ερώτηση:</label>
                <span class="question-id" aria-live="polite">ID Ερώτησης: <span id="question-id">#</span></span>
            </div>

            <div class="form-group question-group">
                <label for="question-text">Κείμενο Ερώτησης:</label>
                <textarea id="question-text" rows="4" placeholder="Πληκτρολογήστε την ερώτησή σας..." required aria-required="true"></textarea>
                <label for="question-media" class="sr-only">Multimedia Ερώτησης:</label>
                <input type="file" id="question-media" name="question_media" accept="image/*,video/*,audio/*" class="file-input">
            </div>

            <div class="form-group">
                <label for="question-explanation">Επεξήγηση Ερώτησης:</label>
                <textarea id="question-explanation" placeholder="Προαιρετική επεξήγηση"></textarea>
                <label for="explanation-media" class="sr-only">Multimedia Επεξήγησης:</label>
                <input type="file" id="explanation-media" name="explanation_media" accept="image/*,video/*,audio/*" class="file-input">
            </div>

            <div class="form-group">
                <label for="question-type">Τύπος Ερώτησης:</label>
                <select id="question-type" required aria-required="true">
                    <option value="single_choice">Πολλαπλής επιλογής (1 σωστή απάντηση)</option>
                    <option value="multiple_choice">Πολλαπλών σωστών απαντήσεων</option>
                    <option value="fill_in_blank">Συμπλήρωση κενών</option>
                </select>
            </div>

            <div id="answers-container" class="answers-container"></div>
            <button type="button" id="add-answer-btn" class="btn-primary">➕ Προσθήκη Νέας Απάντησης</button>

            <button type="submit" class="btn-primary">💾 Αποθήκευση Ερώτησης</button>
        </form>
    </div>

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <script src="../assets/js/question_manager.js" defer></script>
   

    <?php require_once '../includes/admin_footer.php'; ?>
</main>