<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Χρήστης';

if (!isset($user_id) || $user_id === null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User ID not set or null.']);
    exit();
}

if (!$mysqli) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$query = "SELECT fullname, email, phone, address, street_number, postal_code, city, latitude, longitude, avatar 
          FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);

if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to prepare query: ' . $mysqli->error]);
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}

$is_editing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $is_editing = true;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προφίλ Χρήστη - <?= htmlspecialchars($user['fullname']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css">
</head>
<body class="user-page" data-user-id="<?= htmlspecialchars($user_id ?? 0) ?>">
<?php require_once BASE_PATH . '/includes/header.php'; ?>
<div class="container">
    <div class="header">
        <h2>Προφίλ Χρήστη - <?= htmlspecialchars($user['fullname']) ?></h2>
        <p>Διαχειριστείτε τα προσωπικά σας στοιχεία.</p>
    </div>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_GET['success'] === '1' ? 'Το προφίλ ενημερώθηκε με επιτυχία!' : $_GET['success']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>
    <div class="profile-grid" style="display: flex; gap: 20px;">
        <div class="profile-section user-details-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
            <h3>Βασικά Στοιχεία</h3>
            <?php if ($is_editing): ?>
                <form method="POST" enctype="multipart/form-data" id="user-edit-form">
                    <div class="form-group">
                        <label for="fullname" class="form-label">Ονοματεπώνυμο:</label>
                        <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Τηλέφωνο:</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    </div>
                    <div class="form-group avatar-row">
                        <label for="avatar" class="form-label sr-only">Avatar:</label>
                        <div class="avatar-input-container">
                            <input type="file" id="avatar" name="avatar" accept="image/*" class="form-file" style="display: none;">
                            <label for="avatar" class="avatar-upload-btn" style="background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">
                                <span>Ανέβασμα Avatar</span>
                            </label>
                            <?php if ($user['avatar']): ?>
                                <img src="<?= $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) ?>" class="user-avatar" alt="Avatar χρήστη" style="width: 50px; height: 50px; border-radius: 50%; cursor: pointer;">
                            <?php else: ?>
                                <img src="<?= $config['base_url'] . '/uploads/avatars/default.png' ?>" class="user-avatar" alt="Προεπιλεγμένο avatar" style="width: 50px; height: 50px; border-radius: 50%; cursor: pointer;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="save" class="btn-primary save-btn" style="display: none; background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">Αποθήκευση</button>
                </form>
            <?php else: ?>
                <p><strong>Ονοματεπώνυμο:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Τηλέφωνο:</strong> <?= htmlspecialchars($user['phone'] ?? 'Δεν έχει οριστεί') ?></p>
                <div class="avatar-upload">
                    <img src="<?= !empty($user['avatar']) ? $config['base_url'] . '/uploads/avatars/' . basename($user['avatar']) : $config['base_url'] . '/uploads/avatars/default.png' ?>" 
                         alt="Avatar χρήστη <?= htmlspecialchars($user['fullname']) ?>" onclick="document.getElementById('avatarInput').click();" style="width: 50px; height: 50px; border-radius: 50%; cursor: pointer;">
                    <input type="file" id="avatarInput" name="avatar" style="display: none;" onchange="uploadAvatar(this.files)">
                </div>
            <?php endif; ?>
        </div>
        <div class="profile-section address-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
            <h3>Στοιχεία Διεύθυνσης</h3>
            <?php if ($is_editing): ?>
                <div class="form-group">
                    <label for="address" class="form-label">Διεύθυνση:</label>
                    <input type="text" id="autocomplete" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label for="street_number" class="form-label">Αριθμός Οδού:</label>
                    <input type="text" id="street_number" name="street_number" value="<?= htmlspecialchars($user['street_number'] ?? '') ?>" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label for="postal_code" class="form-label">Ταχυδρομικός Κώδικας:</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label for="city" class="form-label">Πόλη:</label>
                    <input type="text" id="city" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
            <?php else: ?>
                <p><strong>Διεύθυνση:</strong> <?= htmlspecialchars($user['address'] ?? 'Δεν έχει οριστεί') ?></p>
                <p><strong>Αριθμός Οδού:</strong> <?= htmlspecialchars($user['street_number'] ?? 'Δεν έχει οριστεί') ?></p>
                <p><strong>Ταχυδρομικός Κώδικας:</strong> <?= htmlspecialchars($user['postal_code'] ?? 'Δεν έχει οριστεί') ?></p>
                <p><strong>Πόλη:</strong> <?= htmlspecialchars($user['city'] ?? 'Δεν έχει οριστεί') ?></p>
                <input type="hidden" id="latitude" value="<?= htmlspecialchars($user['latitude'] ?? '') ?>">
                <input type="hidden" id="longitude" value="<?= htmlspecialchars($user['longitude'] ?? '') ?>">
            <?php endif; ?>
        </div>
        <div class="profile-section map-column" style="flex: 1; background: #f8f8f8; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
            <h3>Τοποθεσία</h3>
            <div id="map" style="height: 400px; width: 100%; margin-top: 10px; border-radius: 8px;"></div>
        </div>
    </div>
    <div class="btn-container" style="width: 100%; margin-top: 20px; display: flex; gap: 10px; justify-content: flex-start;">
        <button type="button" id="edit-btn" class="btn-primary" <?= $is_editing ? 'style="display: none;"' : '' ?>>Επεξεργασία</button>
        <button type="submit" name="save" class="btn-primary save-btn" style="display: <?= $is_editing ? 'inline-block' : 'none' ?>; background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">Αποθήκευση</button>
        <a href="<?= BASE_URL ?>/users/dashboard.php" class="btn-secondary" style="background-color: #c44848; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">Επιστροφή</a>
    </div>
    <?php if ($is_editing): ?>
        </form>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <p class="error-message" style="color: #aa3636; margin-top: 10px; background-color: #ffebee; padding: 10px; border-radius: 5px; margin-bottom: 15px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($config['google_maps_api_key']) ?>&libraries=places" async defer></script>
<script>
    const userId = <?= htmlspecialchars($user_id) ?>;
    const drivetestConfig = { baseUrl: "<?= htmlspecialchars(BASE_URL) ?>" };
</script>
<script src="<?= BASE_URL ?>/assets/js/user_profile.js"></script>
</body>
</html>