<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php'; // ✅ Έλεγχος αν ο χρήστης είναι συνδεδεμένος

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("🚨 Σφάλμα: Δεν έχετε συνδεθεί!");
}

// ✅ Ανάκτηση κατηγοριών τεστ που είναι διαθέσιμες στον χρήστη
$query = "
    SELECT sc.id, sc.name
    FROM subscription_categories sc
    INNER JOIN subscriptions s ON sc.id = s.subscription_category_id
    WHERE s.user_id = ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Έναρξη Τεστ</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<h2>🎯 Επιλογή Κατηγορίας Τεστ</h2>
<form action="test.php" method="GET">
    <label for="category">Επιλέξτε Κατηγορία:</label>
    <select name="category_id" id="category" required>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="test_type">Τύπος Τεστ:</label>
    <select name="test_type" id="test_type">
        <option value="chapter">📚 Τεστ ανά Κεφάλαιο</option>
        <option value="random">🎲 Τυχαίο Τεστ</option>
        <option value="simulation">🕒 Τεστ Προσομοίωσης</option>
        <option value="hard">🔥 Δύσκολες Ερωτήσεις</option>
    </select>

    <button type="submit">🚀 Έναρξη</button>
</form>

<a href="../dashboard.php" class="btn-secondary">🔙 Επιστροφή</a>

</body>
</html>
