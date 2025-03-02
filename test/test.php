<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php'; // ✅ Έλεγχος αν ο χρήστης είναι συνδεδεμένος

$user_id = $_SESSION['user_id'] ?? null;
$category_id = intval($_GET['id'] ?? 0);
$test_type = $_GET['test_type'] ?? 'random';

// ✅ Ελέγχουμε αν ο χρήστης έχει ενεργή συνδρομή στην κατηγορία
$check_query = "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = ? AND id = ?"; // <== Εδώ διορθώνουμε
$stmt_check = $mysqli->prepare($check_query);
$stmt_check->bind_param("ii", $user_id, $category_id);
$stmt_check->execute();
$sub_result = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($sub_result['count'] == 0) {
    die("🚨 Σφάλμα: Δεν έχετε συνδρομή σε αυτή την κατηγορία!");
}

// ✅ Ανάκτηση του σωστού `table_suffix` από τον πίνακα `test_categories`
$suffix_query = "SELECT table_suffix FROM test_categories WHERE subscription_category_id = ?";
$stmt_suffix = $mysqli->prepare($suffix_query);
$stmt_suffix->bind_param("i", $category_id);
$stmt_suffix->execute();
$suffix_result = $stmt_suffix->get_result()->fetch_assoc();
$stmt_suffix->close();

if (!$suffix_result) {
    die("🚨 Σφάλμα: Η κατηγορία τεστ δεν βρέθηκε.");
}

$table_suffix = $suffix_result['table_suffix'];
$table_name = "test_questions_" . $table_suffix;

// ✅ Ανάλογα με τον τύπο τεστ, αλλάζει η επιλογή ερωτήσεων
switch ($test_type) {
    case 'chapter':
        $query = "SELECT * FROM $table_name WHERE chapter = '1' ORDER BY RAND() LIMIT 10";
        break;
    case 'simulation':
        $query = "SELECT * FROM $table_name ORDER BY RAND() LIMIT 20";
        break;
    case 'hard':
        $query = "SELECT * FROM $table_name WHERE difficulty = 'hard' ORDER BY RAND() LIMIT 10";
        break;
    default:
        $query = "SELECT * FROM $table_name ORDER BY RAND() LIMIT 15";
}

$result = $mysqli->query($query);
$questions = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Τεστ</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<h2>📋 Ερωτήσεις Τεστ</h2>
<form action="submit_test.php" method="POST">
    <input type="hidden" name="category_id" value="<?= $category_id ?>">
    <?php foreach ($questions as $index => $q): ?>
        <div class="question">
            <p><strong><?= ($index + 1) ?>.</strong> <?= htmlspecialchars($q['question_text']) ?></p>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php if (!empty($q["answer_$i"])): ?>
                    <label>
                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $i ?>" required>
                        <?= htmlspecialchars($q["answer_$i"]) ?>
                    </label><br>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit">✅ Υποβολή</button>
</form>

</body>
</html>
