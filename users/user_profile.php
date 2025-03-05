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
$is_edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';

// Ανάκτηση των στοιχείων του χρήστη
$query = "SELECT fullname, email, phone, address, street_number, postal_code, city, 
          latitude, longitude, avatar 
          FROM users 
          WHERE id = ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: " . BASE_URL . "/users/dashboard.php?error=" . urlencode("Δεν βρέθηκαν τα στοιχεία του χρήστη"));
    exit();
}

// Επεξεργασία της φόρμας, αν έχει υποβληθεί
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $street_number = trim($_POST['street_number'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    
    // Έλεγχος avatar
    $avatar = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($_FILES['avatar']['type'], $allowed_types)) {
            $upload_dir = '../uploads/avatars/';
            
            // Δημιουργία του φακέλου αν δεν υπάρχει
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatar = 'avatar_' . $user_id . '.' . $file_extension;
            $target_file = $upload_dir . $avatar;
            
            // Μετακίνηση του αρχείου
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                $error = "Σφάλμα κατά το ανέβασμα της εικόνας προφίλ";
            }
        } else {
            $error = "Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο εικόνες JPG, PNG, GIF";
        }
    }
    
    if (!isset($error)) {
        // Ενημέρωση των στοιχείων στη βάση
        $update_query = "UPDATE users 
                         SET fullname = ?, email = ?, phone = ?, 
                             address = ?, street_number = ?, postal_code = ?, city = ?, 
                             latitude = ?, longitude = ?, avatar = ? 
                         WHERE id = ?";
        
        $stmt = $mysqli->prepare($update_query);
        $stmt->bind_param("sssssssdssi", 
            $fullname, $email, $phone, 
            $address, $street_number, $postal_code, $city, 
            $latitude, $longitude, $avatar, 
            $user_id);
        
        if ($stmt->execute()) {
            // Ενημέρωση των στοιχείων συνεδρίας
            $_SESSION['fullname'] = $fullname;
            
            // Ανακατεύθυνση στη σελίδα προφίλ με μήνυμα επιτυχίας
            header("Location: " . BASE_URL . "/users/user_profile.php?success=" . urlencode("Τα στοιχεία ενημερώθηκαν επιτυχώς"));
            exit();
        } else {
            $error = "Σφάλμα κατά την ενημέρωση των στοιχείων: " . $mysqli->error;
        }
        $stmt->close();
    }
}

// Ορισμός των μεταβλητών για το template
$page_title = "Το Προφίλ μου";
$load_profile_css = true;
$load_map_js = true;

// Φόρτωση του header
require_once BASE_PATH . '/includes/header.php';
?>

<div class="profile-container">
    <div class="profile-header">
        <?php
        $avatar_url = !empty($user['avatar']) 
            ? BASE_URL . '/uploads/avatars/' . $user['avatar'] 
            : BASE_URL . '/assets/images/default-avatar.png';
        ?>
        <img src="<?= $avatar_url ?>" alt="<?= htmlspecialchars($user['fullname']) ?>" class="profile-avatar">
        
        <div class="profile-title">
            <h1><?= htmlspecialchars($user['fullname']) ?></h1>
            <p><?= htmlspecialchars($user['email']) ?></p>
        </div>
        
        <div class="profile-actions">
            <?php if ($is_edit_mode): ?>
                <a href="<?= BASE_URL ?>/users/user_profile.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Ακύρωση
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/users/user_profile.php?edit=1" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Επεξεργασία
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/users/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Επιστροφή
            </a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>
    
    <div class="profile-tabs">
        <ul class="nav-tabs">
            <li class="nav-item">
                <a href="#profile-tab" class="nav-link active" data-tab="profile-tab">
                    <i class="fas fa-user"></i> Προφίλ
                </a>
            </li>
            <li class="nav-item">
                <a href="#address-tab" class="nav-link" data-tab="address-tab">
                    <i class="fas fa-map-marker-alt"></i> Διεύθυνση
                </a>
            </li>
            <li class="nav-item">
                <a href="#security-tab" class="nav-link" data-tab="security-tab">
                    <i class="fas fa-lock"></i> Ασφάλεια
                </a>
            </li>
        </ul>
    </div>
    
    <div class="tab-content">
        <!-- Tab Προφίλ -->
        <div id="profile-tab" class="tab-pane active">
            <?php if ($is_edit_mode): ?>
                <form action="<?= BASE_URL ?>/users/user_profile.php" method="POST" enctype="multipart/form-data" class="profile-form">
                    <div class="profile-section">
                        <h2><i class="fas fa-user"></i> Βασικά Στοιχεία</h2>
                        
                        <div class="avatar-upload">
                            <img src="<?= $avatar_url ?>" alt="Avatar Preview" class="avatar-preview" id="avatar-preview">
                            <input type="file" name="avatar" id="avatar-input" style="display: none;" accept="image/*">
                            <button type="button" class="avatar-upload-btn" onclick="document.getElementById('avatar-input').click()">
                                <i class="fas fa-upload"></i> Αλλαγή Εικόνας
                            </button>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullname" class="form-label">Ονοματεπώνυμο</label>
                                <input type="text" id="fullname" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="form-label">Τηλέφωνο</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h2><i class="fas fa-map-marker-alt"></i> Στοιχεία Διεύθυνσης</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="address" class="form-label">Διεύθυνση</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="street_number" class="form-label">Αριθμός</label>
                                <input type="text" id="street_number" name="street_number" class="form-control" value="<?= htmlspecialchars($user['street_number'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="postal_code" class="form-label">Ταχυδρομικός Κώδικας</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="city" class="form-label">Πόλη</label>
                                <input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="map-container" id="map"></div>
                        
                        <input type="hidden" id="latitude" name="latitude" value="<?= $user['latitude'] ?? '' ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?= $user['longitude'] ?? '' ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Αποθήκευση
                        </button>
                        <a href="<?= BASE_URL ?>/users/user_profile.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Ακύρωση
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="profile-section">
                    <h2><i class="fas fa-user"></i> Βασικά Στοιχεία</h2>
                    
                    <ul class="profile-list">
                        <li class="profile-list-item">
                            <div class="profile-list-label">Ονοματεπώνυμο</div>
                            <div class="profile-list-value"><?= htmlspecialchars($user['fullname']) ?></div>
                        </li>
                        <li class="profile-list-item">
                            <div class="profile-list-label">Email</div>
                            <div class="profile-list-value"><?= htmlspecialchars($user['email']) ?></div>
                        </li>
                        <li class="profile-list-item">
                            <div class="profile-list-label">Τηλέφωνο</div>
                            <div class="profile-list-value">
                                <?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="text-muted">Δεν έχει οριστεί</span>' ?>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="profile-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Στοιχεία Διεύθυνσης</h2>
                    
                    <ul class="profile-list">
                        <li class="profile-list-item">
                            <div class="profile-list-label">Διεύθυνση</div>
                            <div class="profile-list-value">
                                <?php
                                $address_parts = [];
                                if (!empty($user['address'])) $address_parts[] = htmlspecialchars($user['address']);
                                if (!empty($user['street_number'])) $address_parts[] = htmlspecialchars($user['street_number']);
                                echo !empty($address_parts) ? implode(' ', $address_parts) : '<span class="text-muted">Δεν έχει οριστεί</span>';
                                ?>
                            </div>
                        </li>
                        <li class="profile-list-item">
                            <div class="profile-list-label">Ταχυδρομικός Κώδικας</div>
                            <div class="profile-list-value">
                                <?= !empty($user['postal_code']) ? htmlspecialchars($user['postal_code']) : '<span class="text-muted">Δεν έχει οριστεί</span>' ?>
                            </div>
                        </li>
                        <li class="profile-list-item">
                            <div class="profile-list-label">Πόλη</div>
                            <div class="profile-list-value">
                                <?= !empty($user['city']) ? htmlspecialchars($user['city']) : '<span class="text-muted">Δεν έχει οριστεί</span>' ?>
                            </div>
                        </li>
                    </ul>
                    
                    <?php if (!empty($user['latitude']) && !empty($user['longitude'])): ?>
                        <div class="map-container" id="map"></div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Δεν έχει οριστεί τοποθεσία</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Διεύθυνσης -->
        <div id="address-tab" class="tab-pane">
            <div class="profile-section">
                <h2><i class="fas fa-map-marker-alt"></i> Χάρτης Τοποθεσίας</h2>
                
                <?php if (!empty($user['latitude']) && !empty($user['longitude'])): ?>
                    <div class="map-container" id="map-full" style="height: 400px;"></div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>Δεν έχει οριστεί τοποθεσία</p>
                        <?php if (!$is_edit_mode): ?>
                            <a href="<?= BASE_URL ?>/users/user_profile.php?edit=1" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Επεξεργασία Προφίλ
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab Ασφάλειας -->
        <div id="security-tab" class="tab-pane">
            <div class="profile-section">
                <h2><i class="fas fa-lock"></i> Ασφάλεια Λογαριασμού</h2>
                
                <form action="<?= BASE_URL ?>/users/change_password.php" method="POST" class="profile-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Τρέχων Κωδικός</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password" class="form-label">Νέος Κωδικός</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <div class="form-text">
                                Ο κωδικός πρέπει να περιέχει τουλάχιστον 8 χαρακτήρες, ένα κεφαλαίο γράμμα, έναν αριθμό και έναν ειδικό χαρακτήρα.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Επιβεβαίωση Κωδικού</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Αλλαγή Κωδικού
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="profile-section">
                <h2><i class="fas fa-shield-alt"></i> Ασφάλεια Λογαριασμού</h2>
                
                <ul class="profile-list">
                    <li class="profile-list-item">
                        <div class="profile-list-label">Τελευταία Σύνδεση</div>
                        <div class="profile-list-value">
                            <?= isset($_SESSION['login_time']) ? date('d/m/Y H:i', $_SESSION['login_time']) : 'Άγνωστο' ?>
                        </div>
                    </li>
                    <li class="profile-list-item">
                        <div class="profile-list-label">Διεύθυνση IP</div>
                        <div class="profile-list-value">
                            <?= $_SERVER['REMOTE_ADDR'] ?>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Προεπισκόπηση Avatar
    const avatarInput = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');
    
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Χάρτης Google Maps (για την καρτέλα προφίλ)
    const mapElement = document.getElementById('map');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    
    if (mapElement && latitudeInput && longitudeInput) {
        const lat = parseFloat(latitudeInput.value);
        const lng = parseFloat(longitudeInput.value);
        
        if (!isNaN(lat) && !isNaN(lng)) {
            const map = new google.maps.Map(mapElement, {
                center: { lat, lng },
                zoom: 15
            });
            
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                draggable: <?= $is_edit_mode ? 'true' : 'false' ?>
            });
            
            <?php if ($is_edit_mode): ?>
            // Ενημέρωση των συντεταγμένων όταν μετακινείται ο marker
            marker.addListener('dragend', function() {
                const position = marker.getPosition();
                latitudeInput.value = position.lat();
                longitudeInput.value = position.lng();
            });
            
            // Αυτόματη εύρεση διεύθυνσης όταν αλλάζουν τα πεδία
            const addressInput = document.getElementById('address');
            const streetNumberInput = document.getElementById('street_number');
            const postalCodeInput = document.getElementById('postal_code');
            const cityInput = document.getElementById('city');
            
            if (addressInput && streetNumberInput && postalCodeInput && cityInput) {
                const geocoder = new google.maps.Geocoder();
                
                const updateMap = function() {
                    const address = `${addressInput.value} ${streetNumberInput.value}, ${postalCodeInput.value} ${cityInput.value}, Greece`;
                    
                    geocoder.geocode({ address }, function(results, status) {
                        if (status === 'OK' && results[0]) {
                            const position = results[0].geometry.location;
                            map.setCenter(position);
                            marker.setPosition(position);
                            
                            latitudeInput.value = position.lat();
                            longitudeInput.value = position.lng();
                        }
                    });
                };
                
                // Ενημέρωση του χάρτη με καθυστέρηση για να αποφύγουμε πολλά requests
                let timeoutId;
                const handleAddressChange = function() {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(updateMap, 1000);
                };
                
                addressInput.addEventListener('change', handleAddressChange);
                streetNumberInput.addEventListener('change', handleAddressChange);
                postalCodeInput.addEventListener('change', handleAddressChange);
                cityInput.addEventListener('change', handleAddressChange);
            }
            <?php endif; ?>
        } else if (<?= $is_edit_mode ? 'true' : 'false' ?>) {
            // Αρχικοποίηση χάρτη με προεπιλεγμένη τοποθεσία (Αθήνα)
            const map = new google.maps.Map(mapElement, {
                center: { lat: 37.9838, lng: 23.7275 },
                zoom: 7
            });
            
            const marker = new google.maps.Marker({
                position: { lat: 37.9838, lng: 23.7275 },
                map: map,
                draggable: true
            });
            
            // Ενημέρωση των συντεταγμένων όταν μετακινείται ο marker
            marker.addListener('dragend', function() {
                const position = marker.getPosition();
                latitudeInput.value = position.lat();
                longitudeInput.value = position.lng();
            });
        }
    }
    
    // Χάρτης για την καρτέλα τοποθεσίας
    const mapFullElement = document.getElementById('map-full');
    
    if (mapFullElement) {
        const lat = <?= !empty($user['latitude']) ? $user['latitude'] : 'null' ?>;
        const lng = <?= !empty($user['longitude']) ? $user['longitude'] : 'null' ?>;
        
        if (lat !== null && lng !== null) {
            const mapFull = new google.maps.Map(mapFullElement, {
                center: { lat, lng },
                zoom: 15
            });
            
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: mapFull,
                draggable: false
            });
        }
    }
});
</script>

<?php
// Φόρτωση του footer
require_once BASE_PATH . '/includes/footer.php';
?>