<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php'; // Έλεγχος αν είναι admin
require_once __DIR__ . '/includes/admin_header.php';

// Λειτουργία για logging
function log_debug($message) {
    file_put_contents(BASE_PATH . '/admin/test/debug_log.txt', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Ανάκτηση στατιστικών από τη βάση
$users_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM users");
if ($result) {
    $users_count = $result->fetch_assoc()['count'];
    log_debug("Users count: " . $users_count);
} else {
    log_debug("Users query failed: " . $mysqli->error);
}

$schools_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role='school'");
if ($result) {
    $schools_count = $result->fetch_assoc()['count'];
    log_debug("Schools count: " . $schools_count);
} else {
    log_debug("Schools query failed: " . $mysqli->error);
}

// ✅ Διορθωμένος υπολογισμός ερωτήσεων από τον πίνακα questions μόνο
$questions_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM questions WHERE 1=1");
if ($result) {
    $questions_count = $result->fetch_assoc()['count'] ?? 0;
    log_debug("Questions count: " . $questions_count);
} else {
    log_debug("Questions query failed: " . $mysqli->error);
}

// ✅ Υπολογισμός ενεργών συνδρομών από τον πίνακα subscriptions
$active_subscriptions_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'");
if ($result) {
    $active_subscriptions_count = $result->fetch_assoc()['count'] ?? 0;
    log_debug("Active subscriptions count: " . $active_subscriptions_count);
} else {
    log_debug("Active subscriptions query failed: " . $mysqli->error);
}

// ✅ Υπολογισμός ολοκληρωμένων τεστ από τον πίνακα test_results
$tests_count = 0;
$result = $mysqli->query("
    SELECT COUNT(*) as count 
    FROM information_schema.tables 
    WHERE table_schema = 'drivetest' 
    AND table_name = 'test_results'
");
if ($result) {
    $table_exists = $result->fetch_assoc()['count'] > 0;
    log_debug("Test results table exists: " . ($table_exists ? 'Yes' : 'No'));
    if ($table_exists) {
        $result = $mysqli->query("SELECT COUNT(*) as count FROM test_results");
        if ($result) {
            $tests_count = $result->fetch_assoc()['count'];
            log_debug("Tests count: " . $tests_count);
        } else {
            log_debug("Tests count query failed: " . $mysqli->error);
        }
    }
} else {
    log_debug("Test results table check failed: " . $mysqli->error);
}

// ✅ Υπολογισμός εσόδων από ενεργές συνδρομές
$revenue = 0;
$result = $mysqli->query("SELECT SUM(price) as total FROM subscriptions s JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON)) WHERE s.status = 'active'");
if ($result) {
    $revenue = $result->fetch_assoc()['total'] ?? 0;
    log_debug("Revenue: " . number_format($revenue, 2) . " €");
} else {
    log_debug("Revenue query failed: " . $mysqli->error);
}

// ✅ Υπολογισμός κατηγοριών, υποκατηγοριών, και κεφαλαίων
$categories_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM subscription_categories");
if ($result) {
    $categories_count = $result->fetch_assoc()['count'] ?? 0;
    log_debug("Categories count: " . $categories_count);
    if ($categories_count != 6) {
        log_debug("Expected 6 categories, found: " . $categories_count);
    }
} else {
    log_debug("Categories query failed: " . $mysqli->error);
}

$subcategories_count = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM test_categories");
if ($result) {
    $subcategories_count = $result->fetch_assoc()['count'] ?? 0;
    log_debug("Subcategories count: " . $subcategories_count);
    if ($subcategories_count != 6) { // Ενημέρωση σε 6 αντί για 5, βάσει των logs
        log_debug("Expected 6 subcategories, found: " . $subcategories_count);
    }
} else {
    log_debug("Subcategories query failed: " . $mysqli->error);
}

// Υπολογισμός κεφαλαίων (χρησιμοποιώντας test_chapters)
$total_chapters = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM test_chapters");
if ($result) {
    $total_chapters = $result->fetch_assoc()['count'] ?? 0;
    log_debug("Chapters count: " . $total_chapters);
    if ($total_chapters != 3) {
        log_debug("Expected 3 chapters, found: " . $total_chapters);
    }
} else {
    log_debug("Chapters query failed: " . $mysqli->error);
}

?>

<main class="admin-container" role="main" aria-label="Πίνακας Διαχείρισης Admin">
    <div class="dashboard-content" role="region" aria-label="Περιεχόμενο Πίνακα Διαχείρισης">
        <section class="admin-actions" role="navigation" aria-label="Γρήγορες Ενέργειες">
            <h2 class="sr-only">Γρήγορες Ενέργειες</h2>
            <div class="quick-links" role="list">
                <a href="users.php" class="btn-primary" role="listitem" aria-label="Διαχείριση Χρηστών">Διαχείριση Χρηστών</a>
                <a href="admin_subscriptions.php" class="btn-primary" role="listitem" aria-label="Διαχείριση Κατηγοριών">Διαχείριση Κατηγοριών</a>
                <a href="test/manage_subcategories.php" class="btn-primary" role="listitem" aria-label="Διαχείριση Υποκατηγοριών">Διαχείριση Υποκατηγοριών</a>
                <a href="test/manage_chapters.php" class="btn-primary" role="listitem" aria-label="Διαχείριση Κεφαλαίων">Διαχείριση Κεφαλαίων</a>
                <a href="test/manage_questions.php" class="btn-primary" role="listitem" aria-label="Διαχείριση Ερωτήσεων">Διαχείριση Ερωτήσεων</a>
                <!-- Προσθήκη στο admin/dashboard.php στην ενότητα "admin-actions" ή "quick-links" -->
<a href="<?= BASE_URL ?>/admin/test/test_config.php" class="btn-primary" role="listitem" aria-label="Ρυθμίσεις Τεστ">⚙️ Ρυθμίσεις Τεστ</a>
<a href="<?= BASE_URL ?>/admin/test/generate_test.php" class="btn-primary" role="listitem" aria-label="Δημιουργία Τεστ">🧩 Δημιουργία Τεστ</a>
            </div>
        </section>

        <section class="admin-stats" role="region" aria-label="Στατιστικά Διαχείρισης">
            <div class="stat-card" role="article" aria-label="Στατιστικά Χρηστών">
                <h2>Χρήστες</h2>
                <p><?= $users_count ?> εγγεγραμμένοι χρήστες</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Σχολών">
                <h2>Σχολές</h2>
                <p><?= $schools_count ?> εγγεγραμμένες σχολές</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Ερωτήσεων">
                <h2>Ερωτήσεις</h2>
                <p><?= $questions_count ?> διαθέσιμες ερωτήσεις</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Κατηγοριών">
                <h2>Κατηγορίες</h2>
                <p><?= $categories_count ?> Κατηγορίες</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Υποκατηγοριών">
                <h2>Υποκατηγορίες</h2>
                <p><?= $subcategories_count ?> Υποκατηγορίες</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Κεφαλαίων">
                <h2>Κεφάλαια</h2>
                <p><?= $total_chapters ?> Κεφάλαια</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Ολοκληρωμένων Τεστ">
                <h2>Ολοκληρωμένα Τεστ</h2>
                <p><?= $tests_count ?> τεστ έχουν ολοκληρωθεί</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Ενεργών Συνδρομών">
                <h2>Ενεργές Συνδρομές</h2>
                <p><?= $active_subscriptions_count ?> ενεργές συνδρομές</p>
            </div>
            <div class="stat-card" role="article" aria-label="Στατιστικά Εσόδων">
                <h2>Εσοδα</h2>
                <p><?= number_format($revenue, 2) ?> €</p>
            </div>
        </section>
    </div>
</main>

<?php require_once 'includes/admin_footer.php'; ?>