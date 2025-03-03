<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος αν ο χρήστης είναι σχολή
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'school') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$school_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Λήψη του school_id από τον πίνακα schools
$school_query = "SELECT s.id FROM schools s JOIN users u ON s.email = u.email WHERE u.id = ?";
$stmt_school = $mysqli->prepare($school_query);
$stmt_school->bind_param("i", $school_id);
$stmt_school->execute();
$result = $stmt_school->get_result();

if ($result->num_rows === 0) {
    header("Location: " . BASE_URL . "/schools/school_profile.php?error=" . urlencode("Δεν βρέθηκαν στοιχεία σχολής."));
    exit();
}

$school = $result->fetch_assoc();
$stmt_school->close();

// Επεξεργασία αιτημάτων (έγκριση/απόρριψη)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        
        // Έλεγχος αν το αίτημα ανήκει στη συγκεκριμένη σχολή
        $check_query = "SELECT sjr.user_id FROM school_join_requests sjr WHERE sjr.id = ? AND sjr.school_id = ? AND sjr.status = 'pending'";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("ii", $request_id, $school['id']);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if ($check_result->num_rows === 0) {
            $error = "Το αίτημα δεν βρέθηκε ή έχει ήδη απαντηθεί.";
        } else {
            $request = $check_result->fetch_assoc();
            $user_id = $request['user_id'];
            
            // Έλεγχος αν ο χρήστης είναι ήδη μαθητής άλλης σχολής
            $user_check = "SELECT school_id FROM users WHERE id = ?";
            $stmt_user = $mysqli->prepare($user_check);
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $user_result = $stmt_user->get_result();
            $user = $user_result->fetch_assoc();
            $stmt_user->close();
            
            if (!empty($user['school_id']) && $user['school_id'] != $school['id']) {
                $error = "Ο χρήστης είναι ήδη μαθητής σε άλλη σχολή.";
                
                // Ενημέρωση του αιτήματος ως απορριφθέν
                $update_request = "UPDATE school_join_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?";
                $stmt_update = $mysqli->prepare($update_request);
                $stmt_update->bind_param("i", $request_id);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                // Ενημέρωση του χρήστη σε μαθητή της σχολής
                $update_user = "UPDATE users SET role = 'student', school_id = ? WHERE id = ?";
                $stmt_update = $mysqli->prepare($update_user);
                $stmt_update->bind_param("ii", $school['id'], $user_id);
                
                if ($stmt_update->execute()) {
                    // Ενημέρωση του αιτήματος ως εγκεκριμένο
                    $update_request = "UPDATE school_join_requests SET status = 'approved', updated_at = NOW() WHERE id = ?";
                    $stmt_request = $mysqli->prepare($update_request);
                    $stmt_request->bind_param("i", $request_id);
                    $stmt_request->execute();
                    $stmt_request->close();
                    
                    // Ειδοποίηση του μαθητή με email
                    $user_query = "SELECT fullname, email FROM users WHERE id = ?";
                    $stmt_user = $mysqli->prepare($user_query);
                    $stmt_user->bind_param("i", $user_id);
                    $stmt_user->execute();
                    $user_result = $stmt_user->get_result();
                    $user = $user_result->fetch_assoc();
                    $stmt_user->close();
                    
                    $school_name_query = "SELECT name FROM schools WHERE id = ?";
                    $stmt_name = $mysqli->prepare($school_name_query);
                    $stmt_name->bind_param("i", $school['id']);
                    $stmt_name->execute();
                    $name_result = $stmt_name->get_result();
                    $school_name = $name_result->fetch_assoc()['name'];
                    $stmt_name->close();
                    
                    $subject = "Το αίτημά σας για συμμετοχή στη σχολή εγκρίθηκε";
                    $message = "<h2>Καλώς ήρθατε στη σχολή!</h2>
                                <p>Αγαπητέ/ή " . htmlspecialchars($user['fullname']) . ",</p>
                                <p>Το αίτημά σας για συμμετοχή στη σχολή <strong>" . htmlspecialchars($school_name) . "</strong> έχει εγκριθεί.</p>
                                <p>Πλέον έχετε πρόσβαση σε όλο το εκπαιδευτικό υλικό και τα τεστ που παρέχει η σχολή.</p>
                                <p>Μπορείτε να συνδεθείτε στο λογαριασμό σας από τον παρακάτω σύνδεσμο:</p>
                                <p><a href='" . BASE_URL . "/public/login.php' style='padding: 10px 15px; background-color: #aa3636; color: white; text-decoration: none; border-radius: 5px;'>Σύνδεση στο DriveTest</a></p>";
                    
                    require_once '../includes/mailer.php';
                    send_mail($user['email'], $subject, $message);
                    
                    $success = "Ο χρήστης εγκρίθηκε επιτυχώς ως μαθητής.";
                } else {
                    $error = "Σφάλμα κατά την ενημέρωση του χρήστη.";
                }
                $stmt_update->close();
            }
        }
        $stmt_check->close();
    } elseif (isset($_POST['reject']) && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
        
        // Έλεγχος αν το αίτημα ανήκει στη συγκεκριμένη σχολή
        $check_query = "SELECT sjr.user_id FROM school_join_requests sjr WHERE sjr.id = ? AND sjr.school_id = ? AND sjr.status = 'pending'";
        $stmt_check = $mysqli->prepare($check_query);
        $stmt_check->bind_param("ii", $request_id, $school['id']);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if ($check_result->num_rows === 0) {
            $error = "Το αίτημα δεν βρέθηκε ή έχει ήδη απαντηθεί.";
        } else {
            $request = $check_result->fetch_assoc();
            $user_id = $request['user_id'];
            
            // Ενημέρωση του αιτήματος ως απορριφθέν
            $update_request = "UPDATE school_join_requests SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?";
            $stmt_update = $mysqli->prepare($update_request);
            $stmt_update->bind_param("si", $rejection_reason, $request_id);
            
            if ($stmt_update->execute()) {
                // Ειδοποίηση του μαθητή με email
                $user_query = "SELECT fullname, email FROM users WHERE id = ?";
                $stmt_user = $mysqli->prepare($user_query);
                $stmt_user->bind_param("i", $user_id);
                $stmt_user->execute();
                $user_result = $stmt_user->get_result();
                $user = $user_result->fetch_assoc();
                $stmt_user->close();
                
                $school_name_query = "SELECT name FROM schools WHERE id = ?";
                $stmt_name = $mysqli->prepare($school_name_query);
                $stmt_name->bind_param("i", $school['id']);
                $stmt_name->execute();
                $name_result = $stmt_name->get_result();
                $school_name = $name_result->fetch_assoc()['name'];
                $stmt_name->close();
                
                $subject = "Το αίτημά σας για συμμετοχή στη σχολή απορρίφθηκε";
                $message = "<h2>Απόρριψη αιτήματος</h2>
                            <p>Αγαπητέ/ή " . htmlspecialchars($user['fullname']) . ",</p>
                            <p>Το αίτημά σας για συμμετοχή στη σχολή <strong>" . htmlspecialchars($school_name) . "</strong> δεν μπορεί να γίνει δεκτό αυτή τη στιγμή.</p>";
                
                if (!empty($rejection_reason)) {
                    $message .= "<p><strong>Αιτία:</strong> " . htmlspecialchars($rejection_reason) . "</p>";
                }
                
                $message .= "<p>Μπορείτε να αναζητήσετε άλλες σχολές στην πλατφόρμα μας ή να δοκιμάσετε ξανά αργότερα.</p>";
                
                require_once '../includes/mailer.php';
                send_mail($user['email'], $subject, $message);
                
                $success = "Το αίτημα απορρίφθηκε επιτυχώς.";
            } else {
                $error = "Σφάλμα κατά την ενημέρωση του αιτήματος.";
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}

// Λήψη εκκρεμών αιτημάτων
$pending_query = "SELECT sjr.id, sjr.user_id, sjr.notes, sjr.created_at, u.fullname, u.email 
                 FROM school_join_requests sjr 
                 JOIN users u ON sjr.user_id = u.id 
                 WHERE sjr.school_id = ? AND sjr.status = 'pending' 
                 ORDER BY sjr.created_at DESC";
                 
$stmt_pending = $mysqli->prepare($pending_query);
$stmt_pending->bind_param("i", $school['id']);
$stmt_pending->execute();
$pending_result = $stmt_pending->get_result();
$pending_requests = [];

while ($request = $pending_result->fetch_assoc()) {
    $pending_requests[] = $request;
}
$stmt_pending->close();

// Λήψη ιστορικού αιτημάτων
$history_query = "SELECT sjr.id, sjr.user_id, sjr.notes, sjr.status, sjr.rejection_reason, sjr.created_at, sjr.updated_at, 
                 u.fullname, u.email 
                 FROM school_join_requests sjr 
                 JOIN users u ON sjr.user_id = u.id 
                 WHERE sjr.school_id = ? AND sjr.status IN ('approved', 'rejected') 
                 ORDER BY sjr.updated_at DESC 
                 LIMIT 50";
                 
$stmt_history = $mysqli->prepare($history_query);
$stmt_history->bind_param("i", $school['id']);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
$request_history = [];

while ($request = $history_result->fetch_assoc()) {
    $request_history[] = $request;
}
$stmt_history->close();

// Έλεγχος για το όριο μαθητών
$count_query = "SELECT COUNT(*) as count FROM users WHERE school_id = ? AND role = 'student'";
$stmt_count = $mysqli->prepare($count_query);
$stmt_count->bind_param("i", $school['id']);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$current_students = $count_result->fetch_assoc()['count'];
$stmt_count->close();

$limit_query = "SELECT students_limit FROM schools WHERE id = ?";
$stmt_limit = $mysqli->prepare($limit_query);
$stmt_limit->bind_param("i", $school['id']);
$stmt_limit->execute();
$limit_result = $stmt_limit->get_result();
$students_limit = $limit_result->fetch_assoc()['students_limit'];
$stmt_limit->close();

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<style>
    .request-container {
        margin-bottom: 30px;
    }
    .request-card {
        background-color: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .request-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        align-items: flex-start;
    }
    .request-user {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    .request-date {
        color: #777;
        font-size: 14px;
    }
    .request-details {
        margin-bottom: 15px;
    }
    .request-notes {
        background-color: #f9f9f9;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        font-style: italic;
    }
    .request-actions {
        display: flex;
        gap: 10px;
    }
    .btn-approve, .btn-reject, .btn-contact {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
    }
    .btn-approve {
        background-color: #28a745;
        color: white;
    }
    .btn-reject {
        background-color: #dc3545;
        color: white;
    }
    .btn-contact {
        background-color: #17a2b8;
        color: white;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        color: white;
    }
    .status-approved {
        background-color: #28a745;
    }
    .status-rejected {
        background-color: #dc3545;
    }
    .empty-state {
        text-align: center;
        padding: 30px;
        background-color: #f9f9f9;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .empty-state i {
        font-size: 48px;
        color: #ddd;
        margin-bottom: 10px;
    }
    .rejection-reason {
        margin-top: 10px;
        font-style: italic;
        color: #777;
    }
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    .modal-content {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }
    .modal-body {
        margin-bottom: 15px;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .students-limit-info {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 5px solid #17a2b8;
    }
    .limit-warning {
        color: #dc3545;
        font-weight: bold;
    }
</style>

<div class="container">
    <div class="header">
        <h2>Διαχείριση Αιτημάτων Μαθητών</h2>
        <p>Εγκρίνετε ή απορρίψτε αιτήματα συμμετοχής μαθητών στη σχολή σας</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <div class="students-limit-info">
        <h3>Πληροφορίες Ορίου Μαθητών</h3>
        <p>Τρέχων αριθμός μαθητών: <strong><?= $current_students ?> / <?= $students_limit ?></strong></p>
        <?php if ($current_students >= $students_limit): ?>
            <p class="limit-warning">Έχετε φτάσει το όριο μαθητών της σχολής σας. Νέα αιτήματα θα απορρίπτονται αυτόματα.</p>
            <p>Για αύξηση του ορίου μαθητών, επικοινωνήστε με τον διαχειριστή ή αναβαθμίστε τη συνδρομή σας.</p>
            <a href="<?= BASE_URL ?>/subscriptions/buy.php?type=school" class="btn-primary">Αναβάθμιση Συνδρομής</a>
        <?php else: ?>
            <p>Μπορείτε να προσθέσετε ακόμα <strong><?= $students_limit - $current_students ?></strong> μαθητές.</p>
        <?php endif; ?>
    </div>
    
    <ul class="nav-tabs">
        <li class="nav-item">
            <a class="nav-link active" data-tab="pending-tab">Εκκρεμή Αιτήματα (<?= count($pending_requests) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-tab="history-tab">Ιστορικό Αιτημάτων (<?= count($request_history) ?>)</a>
        </li>
    </ul>
    
    <div class="tab-content">
        <div id="pending-tab" class="tab-pane active">
            <div class="request-container">
                <?php if (empty($pending_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Δεν υπάρχουν εκκρεμή αιτήματα</h3>
                        <p>Όλα τα αιτήματα έχουν απαντηθεί.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div class="request-user">
                                    <?= htmlspecialchars($request['fullname']) ?>
                                    <span class="request-email">(<?= htmlspecialchars($request['email']) ?>)</span>
                                </div>
                                <div class="request-date">
                                    Ημερομηνία αιτήματος: <?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <?php if (!empty($request['notes'])): ?>
                                    <div class="request-notes">
                                        <strong>Σημειώσεις:</strong> <?= htmlspecialchars($request['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="request-actions">
                                <form action="" method="post">
                                    <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                    <button type="submit" name="approve" class="btn-approve" <?= ($current_students >= $students_limit) ? 'disabled' : '' ?>>
                                        <i class="fas fa-check"></i> Έγκριση
                                    </button>
                                </form>
                                
                                <button type="button" class="btn-reject" onclick="showRejectModal(<?= $request['id'] ?>)">
                                    <i class="fas fa-times"></i> Απόρριψη
                                </button>
                                
                                <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="btn-contact">
                                    <i class="fas fa-envelope"></i> Επικοινωνία
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="history-tab" class="tab-pane">
            <div class="request-container">
                <?php if (empty($request_history)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>Δεν υπάρχει ιστορικό αιτημάτων</h3>
                        <p>Δεν έχετε εγκρίνει ή απορρίψει κανένα αίτημα ακόμα.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($request_history as $history): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div class="request-user">
                                    <?= htmlspecialchars($history['fullname']) ?>
                                    <span class="request-email">(<?= htmlspecialchars($history['email']) ?>)</span>
                                </div>
                                <div class="request-info">
                                    <div class="request-date">
                                        Ημ/νία αιτήματος: <?= date('d/m/Y H:i', strtotime($history['created_at'])) ?>
                                    </div>
                                    <div class="request-date">
                                        Ημ/νία απάντησης: <?= date('d/m/Y H:i', strtotime($history['updated_at'])) ?>
                                    </div>
                                    <div class="request-status">
                                        <span class="status-badge <?= $history['status'] === 'approved' ? 'status-approved' : 'status-rejected' ?>">
                                            <?= $history['status'] === 'approved' ? 'Εγκρίθηκε' : 'Απορρίφθηκε' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <?php if (!empty($history['notes'])): ?>
                                    <div class="request-notes">
                                        <strong>Σημειώσεις μαθητή:</strong> <?= htmlspecialchars($history['notes']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($history['status'] === 'rejected' && !empty($history['rejection_reason'])): ?>
                                    <div class="rejection-reason">
                                        <strong>Αιτία απόρριψης:</strong> <?= htmlspecialchars($history['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal απόρριψης αιτήματος -->
<div id="rejection-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Απόρριψη Αιτήματος</h3>
            <button type="button" class="modal-close" onclick="hideRejectModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="rejection-form" action="" method="post">
                <input type="hidden" id="rejection-request-id" name="request_id">
                <div class="form-group">
                    <label for="rejection-reason">Αιτία απόρριψης (προαιρετικό):</label>
                    <textarea id="rejection-reason" name="rejection_reason" class="form-control" rows="3" placeholder="Εισάγετε την αιτία απόρριψης..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="hideRejectModal()">Ακύρωση</button>
                    <button type="submit" name="reject" class="btn-reject">Απόρριψη</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabs = document.querySelectorAll('.nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all tab panes
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Show the selected tab pane
            const targetTab = this.getAttribute('data-tab');
            document.getElementById(targetTab).classList.add('active');
        });
    });
});

// Modal functions
function showRejectModal(requestId) {
    document.getElementById('rejection-request-id').value = requestId;
    document.getElementById('rejection-modal').style.display = 'flex';
}

function hideRejectModal() {
    document.getElementById('rejection-modal').style.display = 'none';
    document.getElementById('rejection-form').reset();
}
</script>

<?php require_once '../includes/footer.php'; ?>