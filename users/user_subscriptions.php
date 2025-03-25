<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

// 🔒 Έλεγχος αν είναι συνδεδεμένος
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM subscriptions WHERE user_id = ? ORDER BY expiry_date DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
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

<h2>📜 Οι Συνδρομές μου</h2>

<table>
    <tr>
        <th>Κατηγορίες</th>
        <th>Λήξη</th>
        <th>Ενέργειες</th>
    </tr>
    <?php while ($sub = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($sub['categories']) ?></td>
            <td><?= htmlspecialchars($sub['expiry_date']) ?></td>
            <td>
                <a href="renew_subscription.php?id=<?= $sub['id'] ?>">🔄 Ανανέωση</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
