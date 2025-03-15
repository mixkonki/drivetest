<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['fullname'] ?? 'Χρήστης';

// Ανάκτηση δεδομένων του συνδεδεμένου χρήστη για το subscription_status
$query = "SELECT subscription_status, email, avatar FROM users WHERE id = ?";
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

// Λήψη ιστορικού τεστ
$test_history_query = "SELECT * FROM test_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $mysqli->prepare($test_history_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$test_history_result = $stmt->get_result();
$test_history = $test_history_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Λήψη στατιστικών τεστ
$test_stats_query = "SELECT COUNT(*) as total_tests, 
                            SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_tests,
                            AVG(score/total_questions*100) as avg_score
                     FROM test_results 
                     WHERE user_id = ?";
$stmt = $mysqli->prepare($test_stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$test_stats_result = $stmt->get_result();
$test_stats = $test_stats_result->fetch_assoc();
$stmt->close();

// Ορισμός μεταβλητών για το template
$page_title = "Πίνακας Ελέγχου - " . htmlspecialchars($username);
$load_dashboard_css = true;
$load_chart_js = true;

// Φόρτωση του header
require_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Πίνακας Ελέγχου</h1>
        <p>Καλωσήρθατε, <?= htmlspecialchars($username) ?>! Διαχειριστείτε τις συνδρομές, τα τεστ και παρακολουθήστε την πρόοδό σας.</p>
    </div>
    
    <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>
    
    <!-- Γρήγορες ενέργειες -->
    <section class="quick-actions">
        <a href="<?= BASE_URL ?>/test/start.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-play-circle"></i>
            </div>
            <h3 class="quick-action-title">Νέο Τεστ</h3>
            <p class="quick-action-description">Ξεκινήστε ένα νέο τεστ</p>
        </a>
        
        <a href="<?= BASE_URL ?>/test/history.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-history"></i>
            </div>
            <h3 class="quick-action-title">Ιστορικό</h3>
            <p class="quick-action-description">Δείτε τα προηγούμενα τεστ</p>
        </a>
        
        <a href="<?= BASE_URL ?>/subscriptions/buy.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h3 class="quick-action-title">Αγορά Συνδρομής</h3>
            <p class="quick-action-description">Ανανεώστε ή αγοράστε συνδρομή</p>
        </a>
        
        <a href="<?= BASE_URL ?>/users/user_profile.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-user-cog"></i>
            </div>
            <h3 class="quick-action-title">Το Προφίλ μου</h3>
            <p class="quick-action-description">Επεξεργασία στοιχείων</p>
        </a>
    </section>
    
    <div class="dashboard-grid">
        <!-- Στατιστικά -->
        <div class="dashboard-card">
            <h2><i class="fas fa-chart-line"></i> Στατιστικά Επίδοσης</h2>
            
            <?php if (isset($test_stats) && $test_stats['total_tests'] > 0): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $test_stats['total_tests'] ?></div>
                    <div class="stat-label">Συνολικά Τεστ</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?= $test_stats['passed_tests'] ?></div>
                    <div class="stat-label">Επιτυχημένα</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?= round($test_stats['avg_score'], 1) ?>%</div>
                    <div class="stat-label">Μέσος Όρος</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?= round(($test_stats['passed_tests'] / $test_stats['total_tests']) * 100) ?>%</div>
                    <div class="stat-label">Ποσοστό Επιτυχίας</div>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Δεν έχετε ολοκληρώσει κανένα τεστ ακόμα.</p>
                <a href="<?= BASE_URL ?>/test/start.php" class="btn btn-primary">Ξεκινήστε το πρώτο σας τεστ</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Πρόσφατα τεστ -->
        <div class="dashboard-card">
            <h2><i class="fas fa-history"></i> Πρόσφατα Τεστ</h2>
            
            <?php if (!empty($test_history)): ?>
            <div class="recent-tests">
                <?php foreach ($test_history as $test): 
                    $score_percentage = round(($test['score'] / $test['total_questions']) * 100, 1);
                    $passed = $test['passed'] == 1;
                ?>
                <div class="test-item">
                    <div class="test-icon <?= $passed ? 'passed' : 'failed' ?>">
                        <i class="fas <?= $passed ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    </div>
                    <div class="test-info">
                        <div class="test-title">
                            <?php 
                            switch ($test['test_type']) {
                                case 'random': echo 'Τυχαίο Τεστ'; break;
                                case 'chapter': echo 'Τεστ ανά Κεφάλαιο'; break;
                                case 'simulation': echo 'Τεστ Προσομοίωσης'; break;
                                case 'difficult': echo 'Δύσκολες Ερωτήσεις'; break;
                                default: echo htmlspecialchars($test['test_type']);
                            }
                            ?>
                        </div>
                        <div class="test-meta">
                            <span><?= date('d/m/Y H:i', strtotime($test['created_at'])) ?></span>
                            <span class="test-score <?= $passed ? 'passed' : 'failed' ?>">
                                <?= $test['score'] ?>/<?= $test['total_questions'] ?> (<?= $score_percentage ?>%)
                            </span>
                        </div>
                    </div>
                    <div class="test-action">
                        <a href="<?= BASE_URL ?>/test/review.php?id=<?= $test['id'] ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-eye"></i> Προβολή
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/test/history.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Προβολή όλων
                </a>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>Δεν έχετε ολοκληρώσει κανένα τεστ ακόμα.</p>
                <a href="<?= BASE_URL ?>/test/start.php" class="btn btn-primary">Ξεκινήστε το πρώτο σας τεστ</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Συνδρομές -->
        <div class="dashboard-card">
            <h2><i class="fas fa-credit-card"></i> Ενεργές Συνδρομές</h2>
            
            <?php if (!empty($subscriptions)): ?>
                <?php foreach ($subscriptions as $sub): 
                    // Μετατροπή των JSON σε πίνακες
                    $categories_json = $sub['categories'];
                    $durations_json = $sub['durations'];
                    
                    // Έλεγχος αν είναι ήδη JSON αποκωδικοποιημένο
                    $categories = is_array($categories_json) ? $categories_json : json_decode($categories_json, true);
                    $durations = is_array($durations_json) ? $durations_json : json_decode($durations_json, true);
                    
                    // Μορφοποίηση ημερομηνίας λήξης
                    $expiry_date = new DateTime($sub['expiry_date']);
                    $now = new DateTime();
                    $days_remaining = $now->diff($expiry_date)->days;
                    $expires_soon = $days_remaining <= 7 && $days_remaining > 0;
                    
                    // Για κάθε κατηγορία στη συνδρομή
                    foreach ($categories as $category_id):
                        // Έλεγχος αν υπάρχει το όνομα της κατηγορίας
                        $category_name = $category_names[$category_id] ?? "Άγνωστη Κατηγορία";
                        // Διάρκεια (μήνες)
                        $duration = $durations[$category_id] ?? "N/A";
                ?>
                <div class="subscription-card">
                    <div class="subscription-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="subscription-info">
                        <div class="subscription-title"><?= htmlspecialchars($category_name) ?></div>
                        <div class="subscription-meta">
                            <span>Διάρκεια: <?= htmlspecialchars($duration) ?> μήνες</span>
                            <span class="subscription-expires <?= $expires_soon ? 'soon' : '' ?>">
                                Λήξη: <?= $expiry_date->format('d/m/Y') ?>
                                <?= $expires_soon ? ' (Λήγει σύντομα!)' : '' ?>
                            </span>
                        </div>
                    </div>
                    <div class="subscription-action">
                        <a href="<?= BASE_URL ?>/subscriptions/buy.php?renew=<?= $category_id ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-sync-alt"></i> Ανανέωση
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-credit-card"></i>
                <p>Δεν έχετε ενεργές συνδρομές.</p>
                <a href="<?= BASE_URL ?>/subscriptions/buy.php" class="btn btn-primary">Αγορά συνδρομής</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Προσωπικά στοιχεία -->
        <div class="dashboard-card">
            <h2><i class="fas fa-user"></i> Προσωπικά Στοιχεία</h2>
            
            <div class="profile-info">
                <div class="avatar-section text-center mb-4">
                    <?php 
                    $avatar_url = !empty($user['avatar']) 
                        ? BASE_URL . '/uploads/avatars/' . $user['avatar'] 
                        : BASE_URL . '/assets/images/default-avatar.png';
                    ?>
                    <img src="<?= $avatar_url ?>" alt="<?= htmlspecialchars($username) ?>" class="profile-avatar">
                </div>
                
                <ul class="profile-list">
                    <li class="profile-list-item">
                        <div class="profile-list-label">Ονοματεπώνυμο</div>
                        <div class="profile-list-value"><?= htmlspecialchars($username) ?></div>
                    </li>
                    <li class="profile-list-item">
                        <div class="profile-list-label">Email</div>
                        <div class="profile-list-value"><?= htmlspecialchars($user['email']) ?></div>
                    </li>
                    <li class="profile-list-item">
                        <div class="profile-list-label">Κατάσταση Συνδρομής</div>
                        <div class="profile-list-value">
                            <?php if ($user['subscription_status'] === 'active'): ?>
                                <span class="badge badge-success">Ενεργή</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Ανενεργή</span>
                            <?php endif; ?>
                        </div>
                    </li>
                </ul>
                
                <div class="text-center mt-4">
                    <a href="<?= BASE_URL ?>/users/user_profile.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Επεξεργασία Προφίλ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($test_stats) && $test_stats['total_tests'] > 0): ?>
    // Performance Chart
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const performanceChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Επιτυχημένα', 'Αποτυχημένα'],
            datasets: [{
                data: [
                    <?= $test_stats['passed_tests'] ?>, 
                    <?= $test_stats['total_tests'] - $test_stats['passed_tests'] ?>
                ],
                backgroundColor: [
                    '#4CAF50',  // Πράσινο για επιτυχημένα
                    '#F44336'   // Κόκκινο για αποτυχημένα
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'bottom'
            },
            title: {
                display: true,
                text: 'Αναλογία Επιτυχημένων/Αποτυχημένων Τεστ'
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php
// Φόρτωση του footer
require_once BASE_PATH . '/includes/footer.php';
?>