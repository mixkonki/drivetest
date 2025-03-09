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
    ?>

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
        <table id="questions-table" class="admin-table">
            <thead>
                <tr>
                    <th>Ερώτηση</th>
                    <th>Κατηγορία</th>
                    <th>Απαντήσεις</th>
                    <th>Τύπος</th>
                    <th>Δημιουργία</th>
                    <th>Κατάσταση</th>
                    <th>Συγγραφέας</th>
                    <th>Χρησιμοποιήθηκε</th>
                    <th>ID</th>
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

    <script src="../assets/js/question_manager.js" defer></script>

    <?php require_once '../includes/admin_footer.php'; ?>
</main>