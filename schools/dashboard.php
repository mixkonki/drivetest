<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once $config['includes_path'] . '/db_connection.php';
require_once $config['includes_path'] . '/user_auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Σχολή';

// Ελέγχουμε αν υπάρχει παράμετρος id ή view για προβολή/επεξεργασία
$view_school_id = isset($_GET['view']) ? intval($_GET['view']) : (isset($_GET['id']) ? intval($_GET['id']) : $user_id);

if ($view_school_id != $user_id) {
    // Ανάκτηση δεδομένων της σχολής που θέλουμε να δούμε/επεξεργαστούμε
    $query = "SELECT s.name, s.logo, s.subscription_type, s.subscription_expiry, s.students_limit 
              FROM schools s 
              JOIN users u ON s.id = u.school_id 
              WHERE u.id = ?";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare school query for user_id $view_school_id: " . $mysqli->error);
        header("Location: " . BASE_URL . "/public/login.php?error=query_failed");
        exit();
    }
    $stmt->bind_param("i", $view_school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();

    if (!$school) {
        error_log("School not found for user_id: $view_school_id");
        header("Location: " . BASE_URL . "/public/login.php?error=school_not_found");
        exit();
    }
} else {
    // Λήψη δεδομένων της συνδεδεμένης σχολής
    $query = "SELECT name, logo, subscription_type, subscription_expiry, students_limit FROM schools WHERE id = (SELECT school_id FROM users WHERE id = ?)";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare school query for user_id $user_id: " . $mysqli->error);
        header("Location: " . BASE_URL . "/public/login.php?error=query_failed");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $school = $result->fetch_assoc();
}

$stmt->close();

// Λήψη μαθητών της σχολής
$students_query = "SELECT id, fullname, email, subscription_status, created_at FROM users WHERE school_id = ? AND role = 'student'";
$stmt_students = $mysqli->prepare($students_query);
if (!$stmt_students) {
    error_log("Failed to prepare students query for school_id $view_school_id: " . $mysqli->error);
    $students = new mysqli_result(null); // Δημιουργία κενού result set για να αποφύγουμε σφάλματα
} else {
    $stmt_students->bind_param("i", $view_school_id);
    $stmt_students->execute();
    $students = $stmt_students->get_result();
}
$stmt_students->close();

// Λήψη συνδρομών
$subscriptions_query = "SELECT sc.name, s.status, s.created_at, s.expiry_date FROM subscriptions s 
                       JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON))
                       WHERE s.user_id = ? AND s.status != 'cancelled'";
$stmt_sub = $mysqli->prepare($subscriptions_query);
if (!$stmt_sub) {
    error_log("Failed to prepare subscriptions query for user_id $view_school_id: " . $mysqli->error);
    $subscriptions = new mysqli_result(null); // Δημιουργία κενού result set
} else {
    $stmt_sub->bind_param("i", $view_school_id);
    $stmt_sub->execute();
    $subscriptions = $stmt_sub->get_result();
}
$stmt_sub->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Πίνακας Ελέγχου Σχολής - <?= htmlspecialchars($school['name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .dashboard-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .btn-primary, .btn-danger {
            margin: 10px;
        }
        .school-stats, .school-students, .school-subscriptions {
            margin-top: 20px;
        }
        .school-logo {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<?php require_once $config['includes_path'] . '/header.php'; ?>

<div class="container">
    <h1>Προβολή/Επεξεργασία Σχολής: <?= htmlspecialchars($school['name']) ?></h1>
    <p>Αυτά είναι τα στοιχεία της σχολής.</p>

    <section class="dashboard-section school-stats">
        <h2><i class="fas fa-info-circle"></i> Πληροφορίες Σχολής</h2>
        <?php if ($school['logo']): ?>
            <img src="<?= BASE_URL ?>/uploads/schools/<?= basename($school['logo']) ?>" class="school-logo" alt="Λογότυπο σχολής" aria-label="Λογότυπο">
        <?php endif; ?>
        <p><strong>Τύπος Συνδρομής:</strong> <?= htmlspecialchars($school['subscription_type'] ?? 'Καμία') ?></p>
        <p><strong>Ημερομηνία Λήξης:</strong> <?= htmlspecialchars($school['subscription_expiry'] ? date('d/m/Y', strtotime($school['subscription_expiry'])) : 'Καμία') ?></p>
        <p><strong>Όριο Μαθητών:</strong> <?= htmlspecialchars($school['students_limit']) ?></p>
    </section>

    <section class="dashboard-section school-students">
        <h2><i class="fas fa-users"></i> Μαθητές</h2>
        <?php if ($students->num_rows > 0): ?>
            <table class="admin-table" role="table" aria-label="Λίστα Μαθητών">
                <thead>
                    <tr>
                        <th scope="col">Όνομα</th>
                        <th scope="col">Email</th>
                        <th scope="col">Συνδρομή</th>
                        <th scope="col">Ημερομηνία Εγγραφής</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['fullname']) ?></td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td><?= htmlspecialchars($student['subscription_status']) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($student['created_at']))) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Δεν υπάρχουν μαθητές.</p>
        <?php endif; ?>
    </section>

    <section class="dashboard-section school-subscriptions">
        <h2><i class="fas fa-star"></i> Συνδρομές</h2>
        <?php if ($subscriptions->num_rows > 0): ?>
            <table class="admin-table" role="table" aria-label="Λίστα Συνδρομών">
                <thead>
                    <tr>
                        <th scope="col">Κατηγορία</th>
                        <th scope="col">Κατάσταση</th>
                        <th scope="col">Ημερομηνία Έναρξης</th>
                        <th scope="col">Ημερομηνία Λήξης</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($sub = $subscriptions->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($sub['name']) ?></td>
                            <td><?= htmlspecialchars($sub['status']) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($sub['created_at']))) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($sub['expiry_date']))) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Δεν υπάρχουν ενεργές συνδρομές.</p>
            <a href="<?= BASE_URL ?>/subscriptions/buy.php" class="btn-primary">Αγορά Συνδρομής</a>
        <?php endif; ?>
    </section>

    <div class="btn-container">
        <a href="<?= BASE_URL ?>/subscriptions/buy.php" class="btn-primary">Αγορά Νέας Συνδρομής</a>
        <a href="<?= BASE_URL ?>/profile.php" class="btn-primary">Ρυθμίσεις Λογαριασμού</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn-danger">Αποσύνδεση</a>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn-primary">Επιστροφή</a>
    </div>
</div>

<?php require_once $config['includes_path'] . '/footer.php'; ?>

</body>
</html>