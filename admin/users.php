<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';
?>

<main class="admin-container">
    <div class="user-management-header" role="banner" aria-label="Κεφαλίδα Διαχείρισης Χρηστών">
        <h1 class="admin-title">Διαχείριση Χρηστών</h1>
        
        <form method="POST" action="" class="search-form" id="user-filter-form" role="search" aria-label="Αναζήτηση Χρηστών">
            <input type="text" name="search" placeholder="Αναζήτηση..." value="<?= htmlspecialchars($_POST['search'] ?? '') ?>" aria-label="Αναζήτηση χρηστών">
            
            <div class="filter-dropdown">
                <select name="role" aria-label="Φιλτράρισμα ανά ρόλο">
                    <option value="">Όλοι οι ρόλοι</option>
                    <option value="user" <?= (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : '' ?>>Χρήστες</option>
                    <option value="student" <?= (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : '' ?>>Μαθητές</option>
                    <option value="school" <?= (isset($_POST['role']) && $_POST['role'] == 'school') ? 'selected' : '' ?>>Σχολές</option>
                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Διαχειριστές</option>
                </select>
            </div>
            
            <button type="submit" class="btn-search" aria-label="Υποβολή αναζήτησης">
                <i>🔎</i> Αναζήτηση
            </button>
        </form>

        <div class="action-buttons" role="navigation" aria-label="Κουμπιά Δράσης">
            <a href="dashboard.php" class="btn-secondary" role="button" aria-label="Επιστροφή στη Διαχείριση">
                <i class="nav-icon">⬅️</i> Επιστροφή
            </a>
            <a href="add_user.php" class="btn-primary" role="button" aria-label="Προσθήκη νέου χρήστη">
                <i class="nav-icon">➕</i> Προσθήκη Νέου Χρήστη
            </a>
        </div>
    </div>

    <table class="users-table" role="table" aria-label="Λίστα Χρηστών">
        <thead>
            <tr>
                <th scope="col" class="sortable" data-sort="avatar">Avatar</th>
                <th scope="col" class="sortable" data-sort="fullname">Όνομα</th>
                <th scope="col" class="sortable" data-sort="email">Email</th>
                <th scope="col" class="sortable" data-sort="role">Ρόλος</th>
                <th scope="col" class="sortable" data-sort="school_id">Σχολή</th>
                <th scope="col" class="sortable" data-sort="subscription_status">Συνδρομή</th>
                <th scope="col" class="sortable" data-sort="phone">Τηλέφωνο</th>
                <th scope="col" class="sortable" data-sort="status">Κατάσταση</th>
                <th scope="col" class="sortable" data-sort="created_at">Ημερομηνία Εγγραφής</th>
            </tr>
        </thead>
        <tbody id="users-table-body">
            <?php
            // Ορισμός παραμέτρων ταξινόμησης
            $sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'id';
            $sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
            
            // Βασικό ερώτημα SQL
            $query = "SELECT u.id, u.fullname, u.email, u.role, u.subscription_status, u.avatar, u.created_at, u.status, u.school_id, u.phone, s.name AS school_name 
                      FROM users u 
                      LEFT JOIN schools s ON u.school_id = s.id";
            
            // Προσθήκη φίλτρων αν υπάρχουν
            $where_clauses = [];
            $params = [];
            $types = '';
            
            if (isset($_POST['search']) && !empty($_POST['search'])) {
                $search = trim($_POST['search']);
                $where_clauses[] = "(u.fullname LIKE ? OR u.email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $types .= 'ss';
            }
            
            if (isset($_POST['role']) && !empty($_POST['role'])) {
                $role = trim($_POST['role']);
                $where_clauses[] = "u.role = ?";
                $params[] = $role;
                $types .= 's';
            }
            
            if (!empty($where_clauses)) {
                $query .= " WHERE " . implode(' AND ', $where_clauses);
            }
            
            // Προσθήκη ταξινόμησης
            $query .= " ORDER BY u.$sort_column $sort_order";
            
            // Εκτέλεση του ερωτήματος
            $stmt = $mysqli->prepare($query);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Πίνακας κατηγοριών ρόλων
            $roles = [
                'user' => 'Χρήστης', 
                'student' => 'Μαθητής', 
                'school' => 'Σχολή', 
                'admin' => 'Διαχειριστής'
            ];
            
            // Εμφάνιση χρηστών
            if ($result->num_rows > 0) {
                while ($user = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td>
                            <div class="user-info-tooltip">
                                <img src="<?= !empty($user['avatar']) ? $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) : $config['base_url'] . '/uploads/avatars/default.png' ?>" 
                                     class="user-avatar" alt="Avatar χρήστη <?= htmlspecialchars($user['fullname']) ?>" aria-label="Avatar">
                                <div class="tooltip-content">
                                    <strong><?= htmlspecialchars($user['fullname']) ?></strong><br>
                                    <small>ID: <?= $user['id'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo $config['base_url']; ?>/admin/edit_user.php?id=<?= $user['id'] ?>" class="user-name-link" aria-label="Επεξεργασία χρήστη <?= htmlspecialchars($user['fullname']) ?>">
                                <?= htmlspecialchars($user['fullname']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($roles[$user['role']] ?? 'Άγνωστος') ?></td>
                        <td><?= htmlspecialchars($user['school_name'] ?? 'Καμία') ?></td>
                        <td><?= htmlspecialchars($user['subscription_status']) ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? '') ?></td>
                        <td><?= $user['status'] == 'active' ? '<span class="status-active">Ενεργός</span>' : '<span class="status-inactive">Ανενεργός</span>' ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($user['created_at']))) ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="9" class="no-results">Δεν βρέθηκαν χρήστες.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    
    <?php if (isset($_GET['page'])): ?>
    <div class="pagination">
        <?php
        // Εδώ θα μπει ο κώδικας για το pagination αν χρειάζεται
        $total_pages = 5; // Παράδειγμα - θα υπολογιστεί από το σύνολο των χρηστών
        $current_page = intval($_GET['page'] ?? 1);
        
        if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?>" aria-label="Προηγούμενη σελίδα">❮</a>
        <?php else: ?>
            <span class="disabled">❮</span>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $current_page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?>" aria-label="Επόμενη σελίδα">❯</a>
        <?php else: ?>
            <span class="disabled">❯</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($result->num_rows === 0 && !isset($_POST['search']) && !isset($_POST['role'])): ?>
    <div class="empty-users-state">
        <i>👥</i>
        <p>Δεν υπάρχουν καταχωρημένοι χρήστες.</p>
        <a href="add_user.php" class="btn-primary">Προσθήκη Πρώτου Χρήστη</a>
    </div>
    <?php endif; ?>
</main>

<script src="<?= $config['base_url'] ?>/admin/assets/js/users.js"></script>

<?php require_once 'includes/admin_footer.php'; ?>