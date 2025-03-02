<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_header.php';
?>

<main class="admin-container">
    <div class="user-management-header" role="banner" aria-label="Κεφαλίδα Διαχείρισης Χρηστών">
        <h1 class="admin-title" style="margin-right: 20px; flex: 0 0 auto;">Διαχείριση Χρηστών</h1>
        
        <form method="POST" action="" class="search-form" id="user-filter-form" role="search" aria-label="Αναζήτηση Χρηστών">
            <input type="text" name="search" placeholder="Αναζήτηση..." value="<?= htmlspecialchars($_POST['search'] ?? '') ?>" aria-label="Αναζήτηση χρηστών">
            <select name="role" aria-label="Φιλτράρισμα ανά ρόλο">
                <option value="">Όλοι</option>
                <option value="user" <?= (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : '' ?>>Χρήστες</option>
                <option value="student" <?= (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : '' ?>>Μαθητές</option>
                <option value="school" <?= (isset($_POST['role']) && $_POST['role'] == 'school') ? 'selected' : '' ?>>Σχολές</option>
                <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Διαχειριστές</option>
            </select>
            <button type="submit" class="btn-primary" aria-label="Υποβολή αναζήτησης">🔎 Αναζήτηση</button>
        </form>

        <div class="action-buttons" role="navigation" aria-label="Κουμπιά Δράσης">
            <a href="dashboard.php" class="btn-primary" role="button" aria-label="Επιστροφή στη Διαχείριση">Επιστροφή</a>
            <a href="add_user.php" class="btn-primary" role="button" aria-label="Προσθήκη νέου χρήστη">Προσθήκη Νέου Χρήστη</a>
        </div>
    </div>

    <table class="admin-table users-table" role="table" aria-label="Λίστα Χρηστών">
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
            $sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'id';
            $sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
            $query = "SELECT u.id, u.fullname, u.email, u.role, u.subscription_status, u.avatar, u.created_at, u.status, u.school_id, u.phone, s.name AS school_name 
                      FROM users u 
                      LEFT JOIN schools s ON u.school_id = s.id 
                      ORDER BY u.$sort_column $sort_order";
            $result = $mysqli->query($query);
            $roles = ['user' => 'Χρήστης', 'student' => 'Μαθητής', 'school' => 'Σχολή', 'admin' => 'Διαχειριστής'];
            while ($user = $result->fetch_assoc()) :
            ?>
                <tr>
                    <td>
                        <img src="<?= !empty($user['avatar']) ? $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) : $config['base_url'] . '/uploads/avatars/default.png' ?>" 
                             class="user-avatar" alt="Avatar χρήστη <?= htmlspecialchars($user['fullname']) ?>" aria-label="Avatar">
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
            <?php endwhile; ?>
        </tbody>
    </table>
</main>

<?php require_once 'includes/admin_footer.php'; ?>

<!-- JavaScript για AJAX και Ταξινόμηση -->
<script src="<?php echo $config['base_url']; ?>/admin/assets/js/admin.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('user-filter-form');
    const tableBody = document.getElementById('users-table-body');
    const sortables = document.querySelectorAll('.sortable');

    // AJAX για φιλτράρισμα
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);

        fetch('<?php echo $config['base_url']; ?>/admin/api/users.php?action=filter', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Filter response:', data);
            if (data.success) {
                tableBody.innerHTML = data.html;
            } else {
                tableBody.innerHTML = '<tr><td colspan="9">Καμία εγγραφή δεν βρέθηκε.</td></tr>';
                console.error('Error from server:', data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            tableBody.innerHTML = '<tr><td colspan="9">Σφάλμα κατά την ανάκτηση δεδομένων.</td></tr>';
        });
    });

    // Ταξινόμηση με κλικ στις κεφαλίδες
    sortables.forEach(th => {
        th.addEventListener('click', function() {
            const sortColumn = this.dataset.sort;
            const currentOrder = this.getAttribute('data-order') === 'asc' ? 'desc' : 'asc';
            this.setAttribute('data-order', currentOrder);

            fetch('<?php echo $config['base_url']; ?>/admin/api/users.php?action=sort&column=' + sortColumn + '&order=' + currentOrder)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tableBody.innerHTML = data.html;
                } else {
                    console.error('Sort error:', data.message);
                }
            })
            .catch(error => console.error('Sort fetch error:', error));
        });
    });
});
</script>