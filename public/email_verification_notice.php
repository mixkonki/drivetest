<?php
require_once '../config/config.php';
require_once '../includes/header.php';

$email = isset($_GET['email']) ? $_GET['email'] : '';
$resend_status = isset($_GET['resend']) ? $_GET['resend'] : '';
?>

<main class="verification-container">
    <div class="verification-box">
        <h1>✅ Επιβεβαίωση Email</h1>
        <p>Ένα email επιβεβαίωσης έχει σταλεί στη διεύθυνση:</p>
        <p><strong><?= htmlspecialchars($email) ?></strong></p>
        <p>Παρακαλώ ελέγξτε το email σας και πατήστε τον σύνδεσμο επιβεβαίωσης.</p>

        <?php if ($resend_status === 'success'): ?>
            <p class="success-message">📩 Το email επιβεβαίωσης στάλθηκε ξανά με επιτυχία.</p>
        <?php elseif ($resend_status === 'error'): ?>
            <p class="error-message">⚠ Δεν βρέθηκε ενεργός λογαριασμός για επιβεβαίωση.</p>
        <?php endif; ?>

        <p>Αν δεν λάβατε το email, μπορείτε να το στείλετε ξανά.</p>

        <form action="resend_verification.php" method="post">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <button type="submit" class="btn-primary">📩 Αποστολή Ξανά</button>
        </form>

        <p><a href="<?= BASE_URL ?>/public/login.php" class="btn-secondary">🔑 Σύνδεση</a></p>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>
