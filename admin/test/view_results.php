<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_header.php';

// Λήψη του ID του τεστ
$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;

if ($test_id === 0) {
    die("Δεν έχει οριστεί έγκυρο ID τεστ");
}

// Ανάκτηση των πληροφοριών του τεστ
$test_query = "SELECT tg.*, c.name as category_name 
               FROM test_generation tg
               JOIN test_categories c ON tg.category_id = c.id
               WHERE tg.id = ?";
$test_stmt = $mysqli->prepare($test_query);
$test_stmt->bind_param("i", $test_id);
$test_stmt->execute();
$test = $test_stmt->get_result()->fetch_assoc();

if (!$test) {
    die("Το τεστ δεν βρέθηκε");
}

// Ανάκτηση των αποτελεσμάτων του τεστ
$results_query = "SELECT tr.*, u.fullname, u.email, u.user_type
                 FROM test_results tr
                 JOIN users u ON tr.user_id = u.id
                 WHERE tr.test_id = ?
                 ORDER BY tr.completion_date DESC";
$results_stmt = $mysqli->prepare($results_query);
$results_stmt->bind_param("i", $test_id);
$results_stmt->execute();
$results = $results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Υπολογισμός στατιστικών
$total_results = count($results);
$pass_count = 0;
$total_score = 0;
$completion_times = [];

foreach ($results as $result) {
    if ($result['passed'] == 1) {
        $pass_count++;
    }
    $total_score += $result['score'];
    if ($result['time_taken']) {
        $completion_times[] = $result['time_taken'];
    }
}

$avg_score = $total_results > 0 ? round($total_score / $total_results, 1) : 0;
$pass_rate = $total_results > 0 ? round(($pass_count / $total_results) * 100, 1) : 0;

// Υπολογισμός μέσου χρόνου ολοκλήρωσης
$avg_time = 0;
if (!empty($completion_times)) {
    $avg_seconds = array_sum($completion_times) / count($completion_times);
    $avg_time = round($avg_seconds / 60, 1); // Μετατροπή σε λεπτά
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Αποτελέσματα Τεστ - <?= htmlspecialchars($test['test_name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/admin_styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/assets/css/test_results.css">
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    
    <main class="admin-container">
        <h2 class="admin-title">📊 Αποτελέσματα Τεστ: <?= htmlspecialchars($test['test_name']) ?></h2>
        
        <div class="back-link">
            <a href="quizzes.php" class="btn-secondary">← Επιστροφή στη λίστα τεστ</a>
        </div>
        
        <div class="test-info-panel">
            <div class="test-info-header">
                <h3>Πληροφορίες Τεστ</h3>
            </div>
            <div class="test-info-body">
                <div class="info-row">
                    <div class="info-label">Κατηγορία:</div>
                    <div class="info-value"><?= htmlspecialchars($test['category_name']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ερωτήσεις:</div>
                    <div class="info-value"><?= $test['questions_count'] ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ποσοστό Επιτυχίας:</div>
                    <div class="info-value"><?= $test['pass_percentage'] ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-title">Συνολικές Προσπάθειες</div>
                <div class="stat-value"><?= $total_results ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Ποσοστό Επιτυχίας</div>
                <div class="stat-value"><?= $pass_rate ?>%</div>
                <div class="stat-detail"><?= $pass_count ?> από <?= $total_results ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Μέση Βαθμολογία</div>
                <div class="stat-value"><?= $avg_score ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Μέσος Χρόνος Ολοκλήρωσης</div>
                <div class="stat-value"><?= $avg_time ?> λεπτά</div>
            </div>
        </div>
        
        <?php if (empty($results)): ?>
            <div class="empty-results">
                <p>Δεν υπάρχουν ακόμα αποτελέσματα για αυτό το τεστ.</p>
            </div>
        <?php else: ?>
            <div class="results-actions">
                <div class="search-container">
                    <input type="text" id="search-results" placeholder="Αναζήτηση χρήστη..." class="search-input">
                </div>
                <div class="export-container">
                    <button id="export-csv" class="btn-primary">📥 Εξαγωγή CSV</button>
                </div>
            </div>
            
            <div class="results-table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Χρήστης</th>
                            <th>Email</th>
                            <th>Τύπος</th>
                            <th>Ημερομηνία</th>
                            <th>Βαθμολογία</th>
                            <th>Πέρασε</th>
                            <th>Χρόνος (λεπτά)</th>
                            <th>Ενέργειες</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?= htmlspecialchars($result['fullname']) ?></td>
                                <td><?= htmlspecialchars($result['email']) ?></td>
                                <td><?= htmlspecialchars($result['user_type']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($result['completion_date'])) ?></td>
                                <td><?= $result['score'] ?></td>
                                <td>
                                    <?php if ($result['passed'] == 1): ?>
                                        <span class="badge success">Ναι</span>
                                    <?php else: ?>
                                        <span class="badge danger">Όχι</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $result['time_taken'] ? round($result['time_taken'] / 60, 1) : '-' ?></td>
                                <td>
                                    <a href="view_user_result.php?id=<?= $result['id'] ?>" class="btn-icon view-btn" title="Προβολή Λεπτομερειών">👁️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Αναζήτηση αποτελεσμάτων
            const searchInput = document.getElementById('search-results');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchText = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.results-table tbody tr');
                    
                    rows.forEach(row => {
                        const userName = row.cells[0].textContent.toLowerCase();
                        const userEmail = row.cells[1].textContent.toLowerCase();
                        
                        if (userName.includes(searchText) || userEmail.includes(searchText)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Εξαγωγή σε CSV
            const exportBtn = document.getElementById('export-csv');
            
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    const table = document.querySelector('.results-table');
                    const rows = table.querySelectorAll('tr');
                    let csv = [];
                    
                    // Επικεφαλίδες
                    const headers = [];
                    table.querySelectorAll('thead th').forEach(th => {
                        headers.push(th.textContent);
                    });
                    csv.push(headers.join(','));
                    
                    // Δεδομένα
                    table.querySelectorAll('tbody tr').forEach(tr => {
                        const rowData = [];
                        tr.querySelectorAll('td').forEach((td, index) => {
                            // Εξαίρεση της στήλης Ενέργειες
                            if (index < 7) {
                                let content = td.textContent.trim();
                                // Αντικατάσταση των κομμάτων για να μην επηρεάσουν το CSV
                                content = content.replace(/,/g, ' ');
                                rowData.push(content);
                            }
                        });
                        csv.push(rowData.join(','));
                    });
                    
                    // Δημιουργία και λήψη του αρχείου
                    const csvContent = csv.join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    
                    const link = document.createElement('a');
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'results_<?= $test_id ?>_<?= date('Ymd') ?>.csv');
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
        });
    </script>
    
    <?php require_once '../includes/admin_footer.php'; ?>
</body>
</html>