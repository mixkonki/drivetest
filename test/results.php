<?php
// Διαδρομή: /test/results.php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος αν υπάρχουν αποτελέσματα
if (!isset($_SESSION['test_result'])) {
    header("Location: start.php");
    exit();
}

$result = $_SESSION['test_result'];
$score = round($result['score'], 1);
$passed = $result['passed'];

// Μετατροπή χρόνου σε ώρες:λεπτά:δευτερόλεπτα
$hours = floor($result['time_spent'] / 3600);
$minutes = floor(($result['time_spent'] % 3600) / 60);
$seconds = $result['time_spent'] % 60;
$time_formatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

// Ανάκτηση του ονόματος κατηγορίας
$category_query = "SELECT name FROM subscription_categories WHERE id = ?";
$stmt = $mysqli->prepare($category_query);
$stmt->bind_param("i", $result['test']['category_id']);
$stmt->execute();
$category_result = $stmt->get_result();
$category_name = $category_result->fetch_assoc()['name'] ?? 'Άγνωστη κατηγορία';
$stmt->close();

// Ανάκτηση του ονόματος κεφαλαίου αν υπάρχει
$chapter_name = '';
if (!empty($result['test']['chapter_id'])) {
    $chapter_query = "SELECT name FROM test_chapters WHERE id = ?";
    $stmt = $mysqli->prepare($chapter_query);
    $stmt->bind_param("i", $result['test']['chapter_id']);
    $stmt->execute();
    $chapter_result = $stmt->get_result();
    $chapter_name = $chapter_result->fetch_assoc()['name'] ?? '';
    $stmt->close();
}

// Προετοιμασία δεδομένων για γράφημα
$chart_data = [
    'correct' => $result['correct_answers'],
    'incorrect' => $result['total_questions'] - $result['correct_answers'] - $result['unanswered_count'],
    'unanswered' => $result['unanswered_count']
];
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Αποτελέσματα Τεστ - DriveTest</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/test_results.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="main-container">
        <header class="site-header">
            <div class="logo">
                <a href="<?= BASE_URL ?>"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="DriveTest Logo"></a>
            </div>
            <nav>
                <ul>
                    <li><a href="<?= BASE_URL ?>/dashboard.php">Αρχική</a></li>
                    <li><a href="<?= BASE_URL ?>/test/history.php">Ιστορικό</a></li>
                    <li><a href="<?= BASE_URL ?>/profile.php">Προφίλ</a></li>
                    <li><a href="<?= BASE_URL ?>/logout.php">Αποσύνδεση</a></li>
                </ul>
            </nav>
        </header>

        <div class="results-container">
            <h1><?= $passed ? '🎉 Συγχαρητήρια!' : '📝 Αποτελέσματα Τεστ' ?></h1>
            
            <?php if ($result['is_timeout']): ?>
            <div class="timeout-message">
                <p>⏱️ Ο χρόνος σας τελείωσε και το τεστ υποβλήθηκε αυτόματα.</p>
            </div>
            <?php endif; ?>
            
            <div class="results-overview">
                <div class="results-card">
                    <div class="score-container <?= $passed ? 'passed' : 'failed' ?>">
                        <div class="score-value"><?= $score ?>%</div>
                        <div class="score-label"><?= $passed ? 'Επιτυχία' : 'Αποτυχία' ?></div>
                    </div>
                    
                    <div class="results-details">
                        <div class="result-item">
                            <div class="result-label">Κατηγορία</div>
                            <div class="result-value"><?= htmlspecialchars($category_name) ?></div>
                        </div>
                        
                        <?php if (!empty($chapter_name)): ?>
                        <div class="result-item">
                            <div class="result-label">Κεφάλαιο</div>
                            <div class="result-value"><?= htmlspecialchars($chapter_name) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="result-item">
                            <div class="result-label">Τύπος Τεστ</div>
                            <div class="result-value">
                                <?php
                                switch ($result['test']['type']) {
                                    case 'random': echo '🎲 Τυχαίο Τεστ'; break;
                                    case 'chapter': echo '📚 Τεστ ανά Κεφάλαιο'; break;
                                    case 'simulation': echo '🕒 Τεστ Προσομοίωσης'; break;
                                    case 'difficult': echo '🔥 Δύσκολες Ερωτήσεις'; break;
                                    default: echo htmlspecialchars($result['test']['type']);
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="result-item">
                            <div class="result-label">Σωστές Απαντήσεις</div>
                            <div class="result-value"><?= $result['correct_answers'] ?> / <?= $result['total_questions'] ?></div>
                        </div>
                        
                        <div class="result-item">
                            <div class="result-label">Αναπάντητες</div>
                            <div class="result-value"><?= $result['unanswered_count'] ?></div>
                        </div>
                        
                        <div class="result-item">
                            <div class="result-label">Χρόνος</div>
                            <div class="result-value"><?= $time_formatted ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="results-chart">
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>
            
            <div class="results-actions">
                <a href="<?= BASE_URL ?>/test/review.php?id=<?= $result['test_result_id'] ?>" class="btn btn-primary">📝 Αναλυτική Ανασκόπηση</a>
                <a href="<?= BASE_URL ?>/test/start.php" class="btn btn-secondary">🔄 Νέο Τεστ</a>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">🏠 Αρχική</a>
            </div>
            
            <?php if (!$passed): ?>
            <div class="retry-suggestion">
                <p>Μην απογοητεύεστε! Μελετήστε τις λανθασμένες απαντήσεις και δοκιμάστε ξανά.</p>
                <a href="<?= BASE_URL ?>/test/practice.php?result_id=<?= $result['test_result_id'] ?>" class="btn btn-success">🔍 Εξάσκηση στις Λάθος Απαντήσεις</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('resultsChart').getContext('2d');
        const resultsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Σωστές', 'Λανθασμένες', 'Αναπάντητες'],
                datasets: [{
                    data: [
                        <?= $chart_data['correct'] ?>, 
                        <?= $chart_data['incorrect'] ?>, 
                        <?= $chart_data['unanswered'] ?>
                    ],
                    backgroundColor: [
                        '#4CAF50', // Πράσινο για σωστές
                        '#F44336', // Κόκκινο για λανθασμένες
                        '#9E9E9E'  // Γκρι για αναπάντητες
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = <?= $result['total_questions'] ?>;
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>