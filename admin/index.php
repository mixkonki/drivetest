<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php'; // ✅ Έλεγχος αν είναι διαχειριστής
require_once '../includes/admin_header.php';

// ✅ Ανάκτηση διαθέσιμων κατηγοριών τεστ
$query = "SELECT tc.id, sc.name, sc.description 
          FROM test_categories tc
          JOIN subscription_categories sc ON tc.subscription_category_id = sc.id 
          ORDER BY sc.name ASC";
$result = $mysqli->query($query);
$categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<main class="admin-container">
    <h1 class="admin-title">📋 Διαχείριση Τεστ</h1>

    <section class="admin-actions">
        <h2>🛠️ Διαθέσιμες Κατηγορίες Τεστ</h2>
        <table>
            <tr>
                <th>Κατηγορία</th>
                <th>Περιγραφή</th>
                <th>Διαχείριση</th>
            </tr>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= htmlspecialchars($cat['name']) ?></td>
                    <td><?= htmlspecialchars($cat['description'] ?? 'Χωρίς περιγραφή') ?></td>
                    <td>
                        <a href="manage_questions.php?category_id=<?= $cat['id'] ?>" class="btn">📌 Διαχείριση Ερωτήσεων</a>
                        <a href="test_settings.php?category_id=<?= $cat['id'] ?>" class="btn">⚙️ Ρυθμίσεις</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
</main>

<?php require_once '../includes/admin_footer.php'; ?>
