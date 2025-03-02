<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Λήψη ενεργών συνδρομών του χρήστη
$query = "SELECT id, categories, durations, expiry_date FROM subscriptions WHERE user_id = ? AND expiry_date > NOW()";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$subscriptions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Οι Συνδρομές μου</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<?php require_once BASE_PATH . '/includes/header.php'; ?>

<div class="container">
    <h2>📜 Οι Συνδρομές μου</h2>

    <?php if (!empty($subscriptions)): ?>
        <table>
            <tr>
                <th>Κατηγορίες</th>
                <th>Διάρκεια</th>
                <th>Ημερομηνία Λήξης</th>
                <th>Ενέργειες</th>
            </tr>
            <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td><?= htmlspecialchars(str_replace(['{', '}', '"'], '', $sub['categories'])) ?></td>
                    <td><?= htmlspecialchars(str_replace(['{', '}', '"'], '', $sub['durations'])) ?> μήνες</td>
                    <td><?= htmlspecialchars($sub['expiry_date']) ?></td>
                    <td>
                        <a href="renew_subscription.php?id=<?= $sub['id'] ?>" class="btn-primary">🔄 Ανανέωση</a>
                        <a href="cancel_subscription.php?id=<?= $sub['id'] ?>" class="btn-danger" onclick="return confirm('Σίγουρα θέλετε να ακυρώσετε τη συνδρομή;')">❌ Ακύρωση</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="error-message">Δεν έχετε ενεργές συνδρομές.</p>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/users/dashboard.php" class="btn-secondary">🔙 Επιστροφή στον Πίνακα Ελέγχου</a>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

</body>
</html>
