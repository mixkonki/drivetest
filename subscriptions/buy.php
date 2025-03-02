<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Λήψη κατηγοριών συνδρομών από τη βάση
$query = "SELECT id, name, price FROM subscription_categories";
$result = $mysqli->query($query);
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[$row['id']] = $row;
}

// ✅ Λήψη ενεργών συνδρομών χρήστη
$query = "SELECT categories, expiry_date FROM subscriptions WHERE user_id = ? AND expiry_date > NOW()";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_subscriptions = [];

while ($row = $result->fetch_assoc()) {
    $user_categories = json_decode($row['categories'], true);
    if (is_array($user_categories)) {
        foreach ($user_categories as $cat) {
            $active_subscriptions[$cat] = $row['expiry_date']; 
        }
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Αγορά ή Ανανέωση Συνδρομής</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelector("form").addEventListener("submit", function (e) {
                const checkboxes = document.querySelectorAll(".subscription-checkbox:checked");
                if (checkboxes.length === 0) {
                    alert("Παρακαλώ επιλέξτε τουλάχιστον μία συνδρομή!");
                    e.preventDefault();
                    return;
                }

                const durationFields = document.querySelectorAll("select[name^='durations']");
                durationFields.forEach(field => {
                    if (!field.closest("tr").querySelector(".subscription-checkbox").checked) {
                        field.removeAttribute("name");
                    }
                });
            });
        });
    </script>
</head>
<body>
<?php require_once BASE_PATH . '/includes/header.php'; ?>

<div class="container">
    <h2>Αγορά ή Ανανέωση Συνδρομής</h2>
    <?php if (isset($_GET['error'])): ?>
        <p class="error-message">⚠ Παρακαλώ επιλέξτε τουλάχιστον μία συνδρομή!</p>
    <?php endif; ?>

    <form action="process_buy.php" method="POST">
        <table>
            <tr>
                <th>Επιλογή</th>
                <th>Κατηγορία</th>
                <th>Συνδρομή</th>
                <th>Διάρκεια</th>
            </tr>
            <?php foreach ($categories as $id => $data): ?>
                <tr>
                    <td><input type="checkbox" class="subscription-checkbox" name="categories[]" value="<?= $id ?>"></td>
                    <td><?= htmlspecialchars($data['name']) ?></td>
                    <td>
                        <?= isset($active_subscriptions[$id]) ? "Ανανέωση - Λήγει: " . htmlspecialchars($active_subscriptions[$id]) : "Δεν υπάρχει συνδρομή" ?>
                    </td>
                    <td>
                        <select name="durations[<?= $id ?>]">
                            <option value="1">1 Μήνας</option>
                            <option value="3">3 Μήνες</option>
                            <option value="6">6 Μήνες</option>
                            <option value="12">12 Μήνες</option>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit" class="btn-primary">Ολοκλήρωση</button>
    </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
</body>
</html>
