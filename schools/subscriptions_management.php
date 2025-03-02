<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

// 🔒 Έλεγχος αν είναι σχολή
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'school') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$school_id = $_SESSION['user_id'];
$query = "SELECT s.*, u.fullname FROM subscriptions s 
          JOIN users u ON s.user_id = u.id 
          WHERE s.school_id = ? ORDER BY s.expiry_date DESC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Συνδρομών Μαθητών</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<h2>🏫 Διαχείριση Συνδρομών Μαθητών</h2>

<table>
    <tr>
        <th>Μαθητής</th>
        <th>Κατηγορίες</th>
        <th>Λήξη</th>
        <th>Ενέργειες</th>
    </tr>
    <?php while ($sub = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($sub['fullname']) ?></td>
            <td><?= htmlspecialchars($sub['categories']) ?></td>
            <td><?= htmlspecialchars($sub['expiry_date']) ?></td>
            <td>
                <a href="extend_student_subscription.php?id=<?= $sub['id'] ?>">📅 Ανανέωση</a> |
                <a href="remove_student_subscription.php?id=<?= $sub['id'] ?>" onclick="return confirm('Διαγραφή μαθητή από συνδρομή;')">🗑️ Αφαίρεση</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
