<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Χρήστης';

// Ανάκτηση δεδομένων του συνδεδεμένου χρήστη για το subscription_status
$query = "SELECT subscription_status FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
if (!$stmt) {
    error_log("Failed to prepare query for user_id $user_id: " . $mysqli->error);
    header("Location: " . BASE_URL . "/public/login.php?error=query_failed");
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    error_log("User not found for ID: $user_id");
    header("Location: " . BASE_URL . "/public/login.php?error=user_not_found");
    exit();
}

// ✅ Λήψη ενεργών συνδρομών του χρήστη
$query = "SELECT categories, durations, expiry_date FROM subscriptions WHERE user_id = ? AND status = 'active' AND expiry_date > NOW()";
$stmt = $mysqli->prepare($query);
if (!$stmt) {
    error_log("Failed to prepare subscriptions query for user_id $user_id: " . $mysqli->error);
    $subscriptions = [];
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

// ✅ Λήψη των διαθέσιμων κατηγοριών
$categories_query = "SELECT id, name FROM subscription_categories";
$categories_result = $mysqli->query($categories_query);
$category_names = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $category_names[$row['id']] = $row['name'];
    }
} else {
    error_log("Failed to fetch categories: " . $mysqli->error);
    $category_names = [];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Πίνακας Ελέγχου - <?= htmlspecialchars($username) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css">
</head>
<body class="user-page">
<?php require_once BASE_PATH . '/includes/header.php'; ?>

<div class="container">
    <div class="header">
        <h2>Πίνακας Ελέγχου - <?= htmlspecialchars($username) ?></h2>
        <p>Διαχειριστείτε τις συνδρομές, τα τεστ, και τα στατιστικά σας.</p>
    </div>

    <div class="stats-section">
        <h3>Προσωπικά Στοιχεία</h3>
        <p><strong>Όνομα Χρήστη:</strong> <?= htmlspecialchars($username) ?></p>
        <p><strong>Κατάσταση Συνδρομής:</strong> <?= htmlspecialchars($user['subscription_status']) ?></p>
        <a href="<?= BASE_URL ?>/users/user_profile.php" class="btn-primary">Προβολή/Επεξεργασία Προφίλ</a>
    </div>

    <h3>📜 Ενεργές Συνδρομές & Πρόσβαση</h3>

    <?php if (!empty($subscriptions)): ?>
        <table>
            <tr>
                <th>Κατηγορία</th>
                <th>Διάρκεια</th>
                <th>Ημερομηνία Λήξης</th>
                <th>Ενέργειες</th>
            </tr>
            <?php foreach ($subscriptions as $sub): ?>
                <?php 
                $categories = json_decode($sub['categories'], true);
                $durations = json_decode($sub['durations'], true);
                foreach ($categories as $category_id) {
                    $category_name = $category_names[$category_id] ?? "Άγνωστη Κατηγορία";
                    $duration = $durations[$category_id] ?? "Άγνωστη Διάρκεια";
                    
                    // ✅ Μορφοποίηση ημερομηνίας στα ελληνικά
                    setlocale(LC_TIME, 'el_GR.UTF-8');
                    $formatted_date = date("d - M - Y", strtotime($sub['expiry_date']));
                    $formatted_date = str_replace(
                        ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μάι', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'],
                        $formatted_date
                    );
                ?>
                <tr>
                    <td><?= htmlspecialchars($category_name) ?></td>
                    <td><?= htmlspecialchars($duration) ?> μήνες</td>
                    <td><?= htmlspecialchars($formatted_date) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/subscriptions/buy.php?renew=<?= $category_id ?>" class="btn-secondary">Ανανέωση</a>
                    </td>
                </tr>
                <?php } ?>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="error-message">Δεν έχετε ενεργές συνδρομές.</p>
    <?php endif; ?>

    <div class="btn-container">
        <a href="<?= BASE_URL ?>/subscriptions/buy.php" class="btn-primary">Αγορά Νέας Συνδρομής</a>
        <?php if (!empty($subscriptions)): ?>
            <a href="<?= BASE_URL ?>/test/test.php" class="btn-primary">Πρόσβαση στα Τεστ</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/users/user_profile.php" class="btn-primary">Προφίλ Χρήστη</a>
    </div>

    <div class="stats-section">
        <h3>Στατιστικά (Προσεχώς)</h3>
        <p>Εδώ θα εμφανίζονται τα στατιστικά σας (π.χ. προόδου σε τεστ, βαθμολογίες). Λειτουργία υπό ανάπτυξη.</p>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

</body>
</html>