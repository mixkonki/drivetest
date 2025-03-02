<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';

$user_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

// Ανάκτηση δεδομένων χρήστη
$stmt = $mysqli->prepare("SELECT id, fullname, email, role, avatar, created_at, subscription_status, status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: users.php?error=user_not_found");
    exit();
}

// Ανάκτηση συνδρομών
$subscriptions_query = "SELECT sc.name, s.status, s.created_at, s.expiry_date FROM subscriptions s 
                       JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON))
                       WHERE s.user_id = ? AND s.status != 'cancelled'";
$stmt_sub = $mysqli->prepare($subscriptions_query);
$stmt_sub->bind_param("i", $user_id);
$stmt_sub->execute();
$subscriptions = $stmt_sub->get_result();

// Ανάκτηση μαθητών (για school)
$students = null;
if ($user['role'] === 'school') {
    $students_query = "SELECT id, fullname, email, subscription_status, created_at FROM users WHERE school_id = (SELECT school_id FROM users WHERE id = ?) AND role = 'student'";
    $stmt_students = $mysqli->prepare($students_query);
    $stmt_students->bind_param("i", $user_id);
    $stmt_students->execute();
    $students = $stmt_students->get_result();
}
?>

<main class="admin-container">
    <h1 class="admin-title">Προβολή Χρήστη: <?= htmlspecialchars($user['fullname']) ?></h1>

    <div class="user-details" role="region" aria-label="Λεπτομέρειες Χρήστη">
        <div class="user-info-card">
            <img src="<?= !empty($user['avatar']) ? $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) : $config['base_url'] . '/uploads/avatars/default.png' ?>" 
                 class="user-avatar" alt="Avatar χρήστη <?= htmlspecialchars($user['fullname']) ?>" aria-label="Avatar">
            <p><strong>Όνομα:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p><strong>Ρόλος:</strong> <?= htmlspecialchars($user['role']) ?></p>
            <p><strong>Συνδρομή:</strong> <?= htmlspecialchars($user['subscription_status']) ?></p>
            <p><strong>Κατάσταση:</strong> <?= $user['status'] == 'active' ? 'Ενεργός' : 'Ανενεργός' ?></p>
            <p><strong>Ημερομηνία Εγγραφής:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))) ?></p>
        </div>

        <?php if ($user['role'] === 'user' || $user['role'] === 'school'): ?>
        <div class="user-subscriptions" role="region" aria-label="Συνδρομές Χρήστη">
            <h2>Συνδρομές</h2>
            <?php if ($subscriptions->num_rows > 0): ?>
                <table class="admin-table" role="table" aria-label="Λίστα Συνδρομών">
                    <thead>
                        <tr>
                            <th scope="col">Κατηγορία</th>
                            <th scope="col">Κατάσταση</th>
                            <th scope="col">Ημερομηνία Έναρξης</th>
                            <th scope="col">Ημερομηνία Λήξης</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sub = $subscriptions->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td><?= htmlspecialchars($sub['status']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($sub['created_at']))) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($sub['expiry_date']))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Δεν υπάρχουν συνδρομές.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'school'): ?>
        <div class="user-students" role="region" aria-label="Μαθητές Σχολής">
            <h2>Μαθητές</h2>
            <?php if ($students && $students->num_rows > 0): ?>
                <table class="admin-table" role="table" aria-label="Λίστα Μαθητών">
                    <thead>
                        <tr>
                            <th scope="col">Όνομα</th>
                            <th scope="col">Email</th>
                            <th scope="col">Συνδρομή</th>
                            <th scope="col">Ημερομηνία Εγγραφής</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['fullname']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td><?= htmlspecialchars($student['subscription_status']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($student['created_at']))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Δεν υπάρχουν μαθητές.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($user['role'] === 'student'): ?>
        <div class="user-school" role="region" aria-label="Σχολή Μαθητή">
            <h2>Σχολή</h2>
            <?php
            $school_query = "SELECT name FROM schools WHERE id = (SELECT school_id FROM users WHERE id = ?)";
            $stmt_school = $mysqli->prepare($school_query);
            $stmt_school->bind_param("i", $user_id);
            $stmt_school->execute();
            $school_result = $stmt_school->get_result();
            $school = $school_result->fetch_assoc();
            ?>
            <p><strong>Σχολή:</strong> <?= htmlspecialchars($school['name'] ?? 'Δεν ανήκει σε σχολή') ?></p>
        </div>
        <?php endif; ?>

        <div class="btn-container">
            <a href="users.php" class="btn-primary" aria-label="Επιστροφή στη Διαχείριση Χρηστών">Επιστροφή</a>
            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-primary" aria-label="Επεξεργασία χρήστη <?= htmlspecialchars($user['fullname']) ?>">Επεξεργασία</a>
            <button class="btn-danger delete-btn" data-id="<?= $user['id'] ?>" aria-label="Διαγραφή χρήστη <?= htmlspecialchars($user['fullname']) ?>">Διαγραφή</button>
            <button class="btn-secondary toggle-status-btn" data-id="<?= $user['id'] ?>" data-status="<?= $user['status'] ?>" aria-label="Αλλαγή κατάστασης χρήστη <?= htmlspecialchars($user['fullname']) ?>">
                <?= $user['status'] == 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?>
            </button>
            <button class="btn-secondary reset-password-btn" data-id="<?= $user['id'] ?>" data-email="<?= htmlspecialchars($user['email']) ?>" aria-label="Επαναφορά κωδικού για χρήστη <?= htmlspecialchars($user['fullname']) ?>">Επαναφορά Κωδ.</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτόν τον χρήστη;')) {
                    const id = this.dataset.id;
                    fetch('<?php echo $config['base_url']; ?>/admin/api/users.php?action=delete&id=' + id, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Ο χρήστης διαγράφηκε επιτυχώς.');
                            window.location.href = 'users.php';
                        } else {
                            alert('Σφάλμα: ' + data.message);
                        }
                    })
                    .catch(error => console.error('Delete error:', error));
                }
            });
        });

        document.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const newStatus = this.dataset.status === 'active' ? 'inactive' : 'active';
                fetch('<?php echo $config['base_url']; ?>/admin/api/users.php?action=toggle_status&id=' + id + '&status=' + newStatus, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.dataset.status = newStatus;
                        this.textContent = newStatus === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση';
                        alert('Η κατάσταση ενημερώθηκε επιτυχώς.');
                        window.location.reload(); // Ανανέωση σελίδας για ενημέρωση UI
                    } else {
                        alert('Σφάλμα: ' + data.message);
                    }
                })
                .catch(error => console.error('Toggle status error:', error));
            });
        });

        document.querySelectorAll('.reset-password-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const email = this.dataset.email;
                if (confirm('Θέλετε να στείλετε email επαναφοράς κωδικού;')) {
                    fetch('<?php echo $config['base_url']; ?>/admin/api/users.php?action=reset_password&id=' + id + '&email=' + encodeURIComponent(email), { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Email επαναφοράς κωδικού στάλθηκε επιτυχώς.');
                        } else {
                            alert('Σφάλμα: ' + data.message);
                        }
                    })
                    .catch(error => console.error('Reset password error:', error));
                }
            });
        });
    });
    </script>
</main>

<?php require_once 'includes/admin_footer.php'; ?>