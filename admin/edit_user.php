<?php
try {
    require_once '../config/config.php';
    require_once '../includes/db_connection.php';
    require_once 'includes/admin_auth.php';
    require_once 'includes/admin_header.php';

    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    error_log("Starting to fetch user data for ID: $user_id", 3, "C:/wamp64/www/drivetest/debug_log.txt");

    $stmt = $mysqli->prepare("SELECT u.id, u.fullname, u.email, u.address, u.street_number, u.postal_code, u.city, u.latitude, u.longitude, u.avatar, u.role, u.subscription_status, u.status, u.phone, u.school_id, s.name AS school_name, s.license_number, s.responsible_person 
                             FROM users u 
                             LEFT JOIN schools s ON u.school_id = s.id 
                             WHERE u.id = ?");
    if (!$stmt) {
        error_log("Failed to prepare query for user ID $user_id: " . $mysqli->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
        throw new Exception("Σφάλμα κατά την ανάκτηση δεδομένων χρήστη.");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        error_log("User not found for ID: $user_id", 3, "C:/wamp64/www/drivetest/debug_log.txt");
        header("Location: users.php?error=user_not_found");
        exit();
    }

    error_log("Successfully fetched user data for ID: $user_id - Name: " . $user['fullname'], 3, "C:/wamp64/www/drivetest/debug_log.txt");

    $roles = ['user' => 'Χρήστης', 'student' => 'Μαθητής', 'school' => 'Σχολή', 'admin' => 'Διαχειριστής'];

    error_log("Starting to fetch schools for dropdown", 3, "C:/wamp64/www/drivetest/debug_log.txt");
    $schools_query = "SELECT id, name FROM schools ORDER BY name";
    $schools_result = $mysqli->query($schools_query);
    if (!$schools_result) {
        error_log("Failed to fetch schools: " . $mysqli->error, 3, "C:/wamp64/www/drivetest/debug_log.txt");
    }

    error_log("Starting to fetch subscriptions for user ID: $user_id", 3, "C:/wamp64/www/drivetest/debug_log.txt");
    $subscriptions_query = "SELECT sc.name, s.status, s.created_at, s.expiry_date FROM subscriptions s 
                           JOIN subscription_categories sc ON JSON_CONTAINS(s.categories, CAST(sc.id AS JSON))
                           WHERE s.user_id = ? AND s.status != 'cancelled'";
    $stmt_sub = $mysqli->prepare($subscriptions_query);
    $stmt_sub->bind_param("i", $user_id);
    $stmt_sub->execute();
    $subscriptions = $stmt_sub->get_result();

    $is_editable = isset($_GET['edit']) && $_GET['edit'] === 'true';
    error_log("Editable state for user ID $user_id: " . ($is_editable ? 'true' : 'false'), 3, "C:/wamp64/www/drivetest/debug_log.txt");

    $default_avatar = '../uploads/avatars/default.png';
    if (!file_exists($default_avatar)) {
        $default_avatar = 'https://via.placeholder.com/50';
    }
} catch (Exception $e) {
    error_log("Error in edit_user.php: " . $e->getMessage(), 3, "C:/wamp64/www/drivetest/debug_log.txt");
    echo "<p class='error-message'>Σφάλμα: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}
?>

<main class="admin-container">
    <h1 class="admin-title" style="text-align: left;">Επεξεργασία Χρήστη: <?= htmlspecialchars($user['fullname']) ?></h1>

    <div class="user-edit-block" role="region" aria-label="Διαχείριση Χρήστη">
        <form action="" method="post" enctype="multipart/form-data" class="admin-form" id="user-edit-form" role="form" aria-label="Φόρμα Επεξεργασίας Χρήστη" data-user-id="<?= $user_id ?>">
            <input type="hidden" name="action" value="update">

            <div class="user-columns" style="display: flex; gap: 20px; margin-bottom: 20px;">
                <!-- 1η Στήλη: Βασικά Στοιχεία -->
                <div class="user-column user-details-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <h2 class="section-title">Βασικά Στοιχεία</h2>
                    <div class="form-group">
                        <label for="fullname" class="form-label">Ονοματεπώνυμο:</label>
                        <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" <?= $is_editable ? 'required' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" <?= $is_editable ? 'required' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Τηλέφωνο:</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group avatar-row">
                        <label for="avatar" class="form-label sr-only">Avatar:</label>
                        <div class="avatar-input-container">
                            <input type="file" id="avatar" name="avatar" accept="image/*" class="form-file" style="display: none;" <?= $is_editable ? '' : 'disabled' ?>>
                            <label for="avatar" class="avatar-upload-btn" <?= $is_editable ? '' : 'style="pointer-events: none; opacity: 0.6;"' ?>>
                                <span>Ανέβασμα Avatar</span>
                            </label>
                            <?php if ($user['avatar']): ?>
                                <img src="<?= $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) ?>" class="user-avatar" alt="Avatar χρήστη">
                            <?php else: ?>
                                <img src="<?= $default_avatar ?>" class="user-avatar" alt="Προεπιλεγμένο avatar">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 2η Στήλη: Ρυθμίσεις Χρήστη -->
                <div class="user-column dropdown-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <h2 class="section-title">Ρυθμίσεις Χρήστη</h2>
                    <div class="form-group">
                        <label for="role" class="form-label">Ρόλος:</label>
                        <select id="role" name="role" <?= $is_editable ? 'required' : 'disabled' ?> onchange="updateFields()" class="form-select" style="background: #fff;">
                            <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>Χρήστης</option>
                            <option value="student" <?= $user['role'] == 'student' ? 'selected' : '' ?>>Μαθητής</option>
                            <option value="school" <?= $user['role'] == 'school' ? 'selected' : '' ?>>Σχολή</option>
                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Διαχειριστής</option>
                        </select>
                    </div>
                    <div class="form-group" id="school-field" style="<?= $user['role'] !== 'student' ? 'display: none;' : '' ?>">
                        <label for="school_id" class="form-label">Σχολή:</label>
                        <select id="school_id" name="school_id" <?= $is_editable ? '' : 'disabled' ?> class="form-select" style="background: #fff;">
                            <option value="">Καμία</option>
                            <?php while ($school = $schools_result->fetch_assoc()): ?>
                                <option value="<?= $school['id'] ?>" <?= $user['school_id'] == $school['id'] ? 'selected' : '' ?>><?= htmlspecialchars($school['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group" id="school-specific-fields" style="<?= $user['role'] !== 'school' ? 'display: none;' : '' ?>">
                        <label for="license_number" class="form-label">Αριθμός Άδειας:</label>
                        <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($user['license_number'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group" id="school-specific-fields-responsible" style="<?= $user['role'] !== 'school' ? 'display: none;' : '' ?>">
                        <label for="responsible_person" class="form-label">Υπεύθυνος Διευθυντής:</label>
                        <input type="text" id="responsible_person" name="responsible_person" value="<?= htmlspecialchars($user['responsible_person'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group">
                        <label for="subscription_status" class="form-label">Κατάσταση Συνδρομής:</label>
                        <select id="subscription_status" name="subscription_status" <?= $is_editable ? 'required' : 'disabled' ?> class="form-select" style="background: #fff;">
                            <option value="pending" <?= $user['subscription_status'] == 'pending' ? 'selected' : '' ?>>Σε Αναμονή</option>
                            <option value="active" <?= $user['subscription_status'] == 'active' ? 'selected' : '' ?>>Ενεργή</option>
                            <option value="expired" <?= $user['subscription_status'] == 'expired' ? 'selected' : '' ?>>Λήξει</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Κατάσταση Λογαριασμού:</label>
                        <select id="status" name="status" <?= $is_editable ? 'required' : 'disabled' ?> class="form-select" style="background: #fff;">
                            <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Ενεργός</option>
                            <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>Ανενεργός</option>
                        </select>
                    </div>
                </div>

                <!-- 3η Στήλη: Στοιχεία Διεύθυνσης -->
                <div class="user-column address-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <h2 class="section-title">Στοιχεία Διεύθυνσης</h2>
                    <div class="form-group">
                        <label for="address" class="form-label">Διεύθυνση:</label>
                        <input type="text" id="autocomplete" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group">
                        <label for="street_number" class="form-label">Αριθμός Οδού:</label>
                        <input type="text" id="street_number" name="street_number" value="<?= htmlspecialchars($user['street_number'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group">
                        <label for="postal_code" class="form-label">Ταχυδρομικός Κώδικας:</label>
                        <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label">Πόλη:</label>
                        <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" <?= $is_editable ? '' : 'readonly' ?> class="form-input" style="background: #fff;">
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
                </div>

                <!-- 4η Στήλη: Χάρτης -->
                <div class="user-column map-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <h2 class="section-title">Χάρτης</h2>
                    <div id="map" style="height: 400px; width: 100%; margin-top: 10px; border-radius: 8px;"></div>
                </div>
            </div>

            <!-- Συνδρομές -->
            <div class="subscriptions-table-container" style="width: 100%; margin-top: 20px; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                <h2 class="section-title">Ενεργές Συνδρομές</h2>
                <?php if ($subscriptions->num_rows > 0): ?>
                    <table class="admin-table subscriptions-table">
                        <thead>
                            <tr>
                                <th scope="col">Κατηγορία</th>
                                <th scope="col">Κατάσταση</th>
                                <th scope="col">Ημερομηνία Λήξης</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sub = $subscriptions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sub['name']) ?></td>
                                    <td><?= htmlspecialchars($sub['status']) ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($sub['expiry_date']))) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Δεν υπάρχουν ενεργές συνδρομές.</p>
                <?php endif; ?>
            </div>

            <!-- Κουμπιά -->
            <div class="btn-container" style="width: 100%; margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-start;">
                <button type="button" class="btn-primary" id="edit-btn" <?= !$is_editable ? '' : 'style="display: none;"' ?>>Επεξεργασία</button>
                <button type="submit" class="btn-primary save-btn" style="display: <?= $is_editable ? 'inline-block' : 'none' ?>;">Αποθήκευση</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='users.php'">Επιστροφή</button>
                <button class="btn-danger delete-btn" data-id="<?= $user_id ?>">Διαγραφή</button>
            </div>
        </form>
    </div>

    <script>
        const drivetestConfig = {
            baseUrl: '<?php echo $config['base_url']; ?>',
            userId: <?= $user_id; ?>,
            googleMapsApiKey: '<?php echo htmlspecialchars($config['google_maps_api_key']); ?>'
        };
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($config['google_maps_api_key']) ?>&libraries=places" async defer></script>
    <script src="<?php echo $config['base_url']; ?>/admin/assets/js/edit_user.js"></script>
</main>

<?php require_once 'includes/admin_footer.php'; ?>