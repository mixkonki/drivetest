<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/school_auth.php'; // Έλεγχος αν είναι συνδεδεμένη σχολή

$school_id = $_SESSION['user_id']; // Η σχολή που είναι συνδεδεμένη

// Λήψη όλων των συνδρομών της σχολής
$query = "SELECT * FROM subscriptions WHERE school_id = ? ORDER BY expiry_date DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$subscriptions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Λήψη μαθητών που ανήκουν στη σχολή
$students_query = "SELECT id, fullname, email FROM users WHERE school_id = ?";
$stmt = $mysqli->prepare($students_query);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Συνδρομών Σχολής</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Διαχείριση Συνδρομών Σχολής</h2>
        
        <h3>Συνδρομές Σχολής</h3>
        <table class="subscriptions-table">
            <tr>
                <th>Κατηγορίες Τεστ</th>
                <th>Ημερομηνία Έναρξης</th>
                <th>Ημερομηνία Λήξης</th>
                <th>Κατάσταση</th>
            </tr>
            <?php foreach ($subscriptions as $sub): ?>
                <tr>
                    <td><?= htmlspecialchars($sub['categories']) ?></td>
                    <td><?= htmlspecialchars($sub['created_at']) ?></td>
                    <td><?= htmlspecialchars($sub['expiry_date']) ?></td>
                    <td><?= (strtotime($sub['expiry_date']) > time()) ? 'Ενεργή' : 'Ληγμένη' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h3>Διαχείριση Μαθητών</h3>
        <table class="students-table">
            <tr>
                <th>Όνομα</th>
                <th>Email</th>
                <th>Ενέργειες</th>
            </tr>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['fullname']) ?></td>
                    <td><?= htmlspecialchars($student['email']) ?></td>
                    <td>
                        <form action="remove_student.php" method="POST">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <button type="submit">Αφαίρεση</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
