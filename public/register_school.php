<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';



$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $school_name = trim($_POST['school_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    // Έλεγχος αν υπάρχει ήδη το email
    $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $error = "Το email είναι ήδη εγγεγραμμένο!";
    } else {
        // Προσθήκη σχολής
        $stmt = $mysqli->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'school')");
        $stmt->bind_param("sss", $school_name, $email, $password);
        
        if ($stmt->execute()) {
            header("Location: login.php?success=registered");
            exit();
        } else {
            $error = "Σφάλμα κατά την εγγραφή!";
        }
    }
    $check_stmt->close();
    $stmt->close();
}

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<main class="container">
    <h1 class="page-title">Εγγραφή Σχολής</h1>
    
    <?php if ($error): ?>
        <p class="error-message"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form action="register_school.php" method="post">
        <label>Όνομα Σχολής:</label>
        <input type="text" name="school_name" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Κωδικός:</label>
        <input type="password" name="password" required>

        <button type="submit" class="button button-secondary">Εγγραφή</button>
    </form>
</main>

<?php require_once '../includes/footer.php'; ?>
