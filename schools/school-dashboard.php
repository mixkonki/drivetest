<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/user_auth.php';

// Έλεγχος αν ο χρήστης είναι σχολή
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'school') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Λήψη στοιχείων σχολής από τους πίνακες users και schools
$query = "SELECT u.id, u.fullname, u.email, u.phone, u.address, u.street_number, u.postal_code, u.city, 
                 u.latitude, u.longitude, u.avatar, s.id as school_id, s.tax_id, s.responsible_person, 
                 s.license_number, s.categories, s.logo, s.website, s.social_links, s.subscription_type, 
                 s.subscription_expiry, s.students_limit
          FROM users u
          LEFT JOIN schools s ON s.email = u.email
          WHERE u.id = ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Δεν βρέθηκαν στοιχεία σχολής. Παρακαλώ επικοινωνήστε με τον διαχειριστή.";
} else {
    $school = $result->fetch_assoc();
    
    // Μετατροπή των κατηγοριών από JSON σε πίνακα αν υπάρχουν
    $school['categories'] = !empty($school['categories']) ? json_decode($school['categories'], true) : [];
    
    // Μετατροπή των κατηγοριών εκπαίδευσης από JSON σε πίνακα αν υπάρχουν
    $school['training_categories'] = !empty($school['training_categories']) ? json_decode($school['training_categories'], true) : [];
    
    // Μετατροπή των social_links από JSON σε πίνακα αν υπάρχουν
    $school['social_links'] = !empty($school['social_links']) ? json_decode($school['social_links'], true) : [];
}
$stmt->close();

// Λήψη διαθέσιμων κοινωνικών δικτύων από τη βάση δεδομένων (υποθετικός πίνακας)
// Αν δεν υπάρχει τέτοιος πίνακας, μπορούμε να ορίσουμε ένα array με τα πιο συνηθισμένα
$available_socials = [
    'facebook' => ['name' => 'Facebook', 'icon' => 'fab fa-facebook'],
    'instagram' => ['name' => 'Instagram', 'icon' => 'fab fa-instagram'],
    'twitter' => ['name' => 'Twitter', 'icon' => 'fab fa-twitter'],
    'linkedin' => ['name' => 'LinkedIn', 'icon' => 'fab fa-linkedin'],
    'youtube' => ['name' => 'YouTube', 'icon' => 'fab fa-youtube'],
    'tiktok' => ['name' => 'TikTok', 'icon' => 'fab fa-tiktok'],
    'pinterest' => ['name' => 'Pinterest', 'icon' => 'fab fa-pinterest'],
    'snapchat' => ['name' => 'Snapchat', 'icon' => 'fab fa-snapchat']
];

// Λήψη των κατηγοριών συνδρομής από τον πίνακα subscription_categories
$cat_query = "SELECT id, name FROM subscription_categories ORDER BY name";
$cat_result = $mysqli->query($cat_query);
$subscription_categories = [];

if ($cat_result) {
    while ($cat = $cat_result->fetch_assoc()) {
        $subscription_categories[$cat['id']] = $cat['name'];
    }
}

// Λήψη των μαθητών της σχολής (συνολικός αριθμός)
$count_query = "SELECT COUNT(*) as total FROM users WHERE school_id = ? AND role = 'student'";
$stmt_count = $mysqli->prepare($count_query);
$stmt_count->bind_param("i", $school['school_id']);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_students = $result_count->fetch_assoc()['total'];
$stmt_count->close();

// Λήψη των ενεργών συνδρομών της σχολής
$subscriptions_query = "SELECT * FROM subscriptions WHERE school_id = ? AND status = 'active' ORDER BY expiry_date DESC LIMIT 1";
$stmt_subs = $mysqli->prepare($subscriptions_query);
$stmt_subs->bind_param("i", $school['school_id']);
$stmt_subs->execute();
$result_subs = $stmt_subs->get_result();
$active_subscription = $result_subs->num_rows > 0 ? $result_subs->fetch_assoc() : null;
$stmt_subs->close();

// Λήψη των εκκρεμών αιτημάτων μαθητών
$pending_query = "SELECT COUNT(*) as total FROM school_join_requests WHERE school_id = ? AND status = 'pending'";
$stmt_pending = $mysqli->prepare($pending_query);
$stmt_pending->bind_param("i", $school['school_id']);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
$pending_requests = $result_pending->fetch_assoc()['total'];
$stmt_pending->close();



// Επεξεργασία της φόρμας ενημέρωσης στοιχείων
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $responsible_person = trim($_POST['responsible_person']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $street_number = trim($_POST['street_number']);
    $postal_code = trim($_POST['postal_code']);
    $city = trim($_POST['city']);
    $website = trim($_POST['website']);
    $latitude = isset($_POST['latitude']) ? trim($_POST['latitude']) : $school['latitude'];
    $longitude = isset($_POST['longitude']) ? trim($_POST['longitude']) : $school['longitude'];
    
    // Social links - επεξεργασία από τη φόρμα και μετατροπή σε JSON
    $social_links = [];
    foreach ($available_socials as $social_key => $social_info) {
        if (isset($_POST['social_' . $social_key]) && !empty($_POST['social_' . $social_key])) {
            $social_links[$social_key] = $_POST['social_' . $social_key];
        }
    }
    $social_links_json = json_encode($social_links);
    
    // Κατηγορίες σχολής
    $school_types = isset($_POST['school_types']) ? $_POST['school_types'] : [];
    $school_types_json = json_encode($school_types);
    
    // Κατηγορίες εκπαίδευσης
    $training_categories = isset($_POST['training_categories']) ? $_POST['training_categories'] : [];
    $training_categories_json = json_encode($training_categories);
    
    // Γεωκωδικοποίηση αν άλλαξε η διεύθυνση και δεν έχουν ενημερωθεί ήδη οι συντεταγμένες
    if (($address != $school['address'] || $street_number != $school['street_number'] || 
         $city != $school['city'] || $postal_code != $school['postal_code']) && 
        !empty($address) && !empty($city) && 
        ($latitude == $school['latitude'] && $longitude == $school['longitude'])) {
        
        $geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . 
                      urlencode($address . " " . $street_number . ", " . $city . ", " . $postal_code . ", Ελλάδα") . 
                      "&key=" . $config['google_maps_api_key'];
        
        $geocode_response = @file_get_contents($geocode_url);
        if ($geocode_response !== false) {
            $geocode_data = json_decode($geocode_response, true);
            if ($geocode_data['status'] === 'OK' && !empty($geocode_data['results'])) {
                $location = $geocode_data['results'][0]['geometry']['location'];
                $latitude = $location['lat'];
                $longitude = $location['lng'];
            }
        }
    }
    
    // Χειρισμός ανεβάσματος λογότυπου
    $logo = $school['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($_FILES['logo']['type'], $allowed_types)) {
            $upload_dir = '../uploads/schools/';
            
            // Δημιουργία του φακέλου αν δεν υπάρχει
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'school_' . $school['school_id'] . '_logo.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                $logo = $filename;
            } else {
                $error = "Αποτυχία ανεβάσματος λογότυπου.";
            }
        } else {
            $error = "Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο εικόνες JPG, PNG και GIF.";
        }
    }
    
    // Ενημέρωση των στοιχείων στη βάση δεδομένων
    $mysqli->begin_transaction();
    
    try {
        // Ενημέρωση στοιχείων χρήστη
        $update_user = "UPDATE users SET 
                        phone = ?, 
                        address = ?, 
                        street_number = ?, 
                        postal_code = ?, 
                        city = ?, 
                        latitude = ?, 
                        longitude = ? 
                        WHERE id = ?";
        
        $stmt_user = $mysqli->prepare($update_user);
        $stmt_user->bind_param("sssssddi", $phone, $address, $street_number, $postal_code, $city, $latitude, $longitude, $user_id);
        
        if (!$stmt_user->execute()) {
            throw new Exception("Σφάλμα κατά την ενημέρωση των στοιχείων χρήστη: " . $mysqli->error);
        }
        $stmt_user->close();
        
        // Ενημέρωση στοιχείων σχολής
        $update_school = "UPDATE schools SET 
                         responsible_person = ?, 
                         address = ?, 
                         street_number = ?, 
                         postal_code = ?, 
                         city = ?, 
                         logo = ?, 
                         website = ?, 
                         social_links = ?,
                         categories = ?,
                         training_categories = ?
                         WHERE id = ?";
        
        $stmt_school = $mysqli->prepare($update_school);
        $stmt_school->bind_param("ssssssssssi", $responsible_person, $address, $street_number, $postal_code, $city, $logo, $website, $social_links_json, $school_types_json, $training_categories_json, $school['school_id']);
        
        if (!$stmt_school->execute()) {
            throw new Exception("Σφάλμα κατά την ενημέρωση των στοιχείων σχολής: " . $mysqli->error);
        }
        $stmt_school->close();
        
        $mysqli->commit();
        $success = "Τα στοιχεία ενημερώθηκαν επιτυχώς!";
        
        // Ανανέωση των στοιχείων στη σελίδα
        $school['phone'] = $phone;
        $school['responsible_person'] = $responsible_person;
        $school['address'] = $address;
        $school['street_number'] = $street_number;
        $school['postal_code'] = $postal_code;
        $school['city'] = $city;
        $school['latitude'] = $latitude;
        $school['longitude'] = $longitude;
        $school['logo'] = $logo;
        $school['website'] = $website;
        $school['social_links'] = $social_links;
        $school['categories'] = $school_types;
        $school['training_categories'] = $training_categories;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $error = $e->getMessage();
    }
}

// Ορίστε μεταβλητές για τη σωστή φόρτωση των στυλ και σεναρίων
$page_title = "Πίνακας Ελέγχου Σχολής - " . ($school['fullname'] ?? 'Σχολή');
$load_map_js = true; // για τη φόρτωση του Google Maps API

// Φόρτωση του header που θα συμπεριλάβει τα στυλ
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <h1>Πίνακας Ελέγχου Σχολής - <?= htmlspecialchars($school['fullname']) ?></h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <div class="dashboard-grid">
            <!-- 1η στήλη: Βασικά στοιχεία σχολής -->
            <div class="dashboard-column">
                <div class="dashboard-card">
                    <h3><i class="fas fa-school"></i> Βασικά Στοιχεία Σχολής</h3>
                    
                    <div class="form-group">
                        <label for="school_name">Επωνυμία Σχολής</label>
                        <input type="text" id="school_name" class="form-control" value="<?= htmlspecialchars($school['fullname']) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($school['email']) ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_id">ΑΦΜ</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="tax_id" class="form-control" value="<?= htmlspecialchars($school['tax_id']) ?>" readonly style="flex: 1;">
                           
                                <i class="fas fa-sync-alt"></i> ΑΑΔΕ
                            </button>
                        </div>
                        <small class="form-text">Άντληση στοιχείων από την ΑΑΔΕ μέσω ΑΦΜ</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="responsible_person">Υπεύθυνος Επικοινωνίας</label>
                        <input type="text" id="responsible_person" name="responsible_person" class="form-control" value="<?= htmlspecialchars($school['responsible_person'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Τηλέφωνο</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($school['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Ιστοσελίδα</label>
                        <input type="url" id="website" name="website" class="form-control" value="<?= htmlspecialchars($school['website'] ?? '') ?>" placeholder="https://www.example.com">
                        <small class="form-text">Συμπληρώστε το πλήρες URL (συμπεριλαμβανομένου του https://)</small>
                    </div>
                    
                    <div class="logo-upload">
                        <label>Λογότυπο Σχολής</label>
                        <?php if (!empty($school['logo'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/schools/<?= htmlspecialchars($school['logo']) ?>" alt="Λογότυπο Σχολής" class="school-logo">
                        <?php else: ?>
                            <p>Δεν έχει οριστεί λογότυπο</p>
                        <?php endif; ?>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="form-text">Προτεινόμενο μέγεθος: 300x300 pixels</small>
                    </div>
                    
                    <div class="social-links">
                        <h4>Κοινωνικά Δίκτυα</h4>
                        
                        <!-- Υπάρχοντα κοινωνικά δίκτυα -->
                        <?php foreach ($school['social_links'] as $social_key => $social_url): ?>
                            <?php if (isset($available_socials[$social_key])): ?>
                                <<div class="social-link">
                <i class="<?= $available_socials[$social_key]['icon'] ?>"></i>
                <input type="url" name="social_<?= $social_key ?>" class="form-control" 
                       placeholder="URL προφίλ <?= $available_socials[$social_key]['name'] ?>" 
                       value="<?= htmlspecialchars($social_url) ?>">
                <button type="button" class="social-delete" onclick="removeSocialLink(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Προσθήκη νέου κοινωνικού δικτύου -->
                        <<div class="social-link-add">
        <select id="social-select" class="form-control social-select">
            <option value="">Προσθήκη κοινωνικού δικτύου</option>
                                <?php foreach ($available_socials as $social_key => $social_info): ?>
                                    <?php if (!isset($school['social_links'][$social_key])): ?>
                                        <option value="<?= $social_key ?>" data-icon="<?= $social_info['icon'] ?>"><?= $social_info['name'] ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </select>
        <button type="button" id="add-social" class="btn-secondary">Προσθήκη</button>
    </div>
    
    <div id="new-social-container"></div>
</div>
                </div>
            </div>
            
            <!-- 2η στήλη: Στοιχεία διεύθυνσης και κατηγορίες -->
            <div class="dashboard-column">
                <div class="dashboard-card">
                    <h3><i class="fas fa-map-marked-alt"></i> Στοιχεία Διεύθυνσης</h3>
                    
                    <div class="form-group">
                        <label for="address">Οδός</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($school['address'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="street_number">Αριθμός</label>
                        <input type="text" id="street_number" name="street_number" class="form-control" value="<?= htmlspecialchars($school['street_number'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="postal_code">Ταχυδρομικός Κώδικας</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?= htmlspecialchars($school['postal_code'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Πόλη</label>
                        <input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars($school['city'] ?? '') ?>">
                    </div>
                    
                    <!-- Κρυφά πεδία για τις συντεταγμένες -->
                    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($school['latitude'] ?? '') ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($school['longitude'] ?? '') ?>">
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-certificate"></i> Κατηγορίες Σχολής</h3>
                    
                    <div class="school-types">
                        <?php
                        $available_types = [
                            'school' => 'Σχολή Οδηγών',
                            'sekam' => 'Σ.ΕΚ.Α.Μ.',
                            'sekoomee' => 'Σ.Ε.Κ.Ο.Ο.Μ.Ε.Ε.',
                            'pei' => 'Σχολή ΠΕΙ',
                            'kedivima' => 'Κε.Δι.Βι.Μα.',
                            'machinery' => 'Σχολή Χειριστών Μηχανημάτων Έργου',
                            'other' => 'Άλλη σχολή'
                        ];
                        
                        foreach ($available_types as $type_key => $type_name):
                        ?>
                            <div class="school-type-option">
                                <input type="checkbox" name="school_types[]" id="type_<?= $type_key ?>" value="<?= $type_key ?>" 
                                       <?= (is_array($school['categories']) && in_array($type_key, $school['categories'])) ? 'checked' : '' ?>>
                                <label for="type_<?= $type_key ?>"><?= $type_name ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-award"></i> Κατηγορίες Εκπαίδευσης</h3>
                    
                    <div class="school-types">
                        <?php
                        // Χρήση των κατηγοριών από τον πίνακα subscription_categories
                        foreach ($subscription_categories as $cat_id => $cat_name):
                        ?>
                            <div class="school-type-option">
                                <input type="checkbox" name="training_categories[]" id="cat_<?= $cat_id ?>" value="<?= $cat_id ?>"
                                       <?= (is_array($school['training_categories']) && in_array($cat_id, $school['training_categories'])) ? 'checked' : '' ?>>
                                <label for="cat_<?= $cat_id ?>"><?= htmlspecialchars($cat_name) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- 3η στήλη: Χάρτης και Στατιστικά -->
            <div class="dashboard-column">
                <div class="dashboard-card">
                    <h3><i class="fas fa-map-marked-alt"></i> Τοποθεσία Σχολής</h3>
                    
                    <div id="map"></div>
                    <small class="form-text">Ο χάρτης ενημερώνεται αυτόματα με τη διεύθυνση που έχετε καταχωρήσει</small>
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-chart-bar"></i> Στατιστικά</h3>
                    
                    <div class="stats-item">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-details">
                            <p class="stats-value"><?= $total_students ?> / <?= $school['students_limit'] ?></p>
                            <p class="stats-label">Μαθητές</p>
                        </div>
                    </div>
                    
                    <div class="stats-item">
                        <div class="stats-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stats-details">
                            <p class="stats-value"><?= $pending_requests ?></p>
                            <p class="stats-label">Εκκρεμή αιτήματα μαθητών</p>
                        </div>
                    </div>
                    
                    <div class="stats-item">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stats-details">
                            <p class="stats-value"><?= $active_subscription ? date('d/m/Y', strtotime($active_subscription['expiry_date'])) : 'Μη διαθέσιμο' ?></p>
                            <p class="stats-label">Λήξη συνδρομής</p>
                        </div>
                    </div>
                    
                    <div class="stats-item">
                        <div class="stats-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stats-details">
                            <p class="stats-value">0</p>
                            <p class="stats-label">Ολοκληρωμένα τεστ</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 4η στήλη: Συντομεύσεις και επιλογές -->
            <div class="dashboard-column">
                <div class="dashboard-card">
                    <h3><i class="fas fa-th-large"></i> Γρήγορη Πρόσβαση</h3>
                    
                    <div class="quick-links">
                        <a href="<?= BASE_URL ?>/schools/school_profile.php" class="quick-link">
                            <i class="fas fa-id-card"></i>
                            <div>
                                <h4>Προφίλ Σχολής</h4>
                                <p>Επεξεργασία του δημόσιου προφίλ της σχολής σας</p>
                            </div>
                        </a>
                        
                        <a href="<?= BASE_URL ?>/schools/manage_students.php" class="quick-link">
                            <i class="fas fa-user-graduate"></i>
                            <div>
                                <h4>Διαχείριση Μαθητών</h4>
                                <p>Προσθήκη, επεξεργασία και διαγραφή μαθητών</p>
                            </div>
                        </a>
                        
                        <a href="<?= BASE_URL ?>/schools/manage_student_requests.php" class="quick-link">
                            <i class="fas fa-user-plus"></i>
                            <div>
                                <h4>Αιτήματα Μαθητών</h4>
                                <p>Διαχείριση αιτημάτων συμμετοχής από μαθητές</p>
                                <?php if ($pending_requests > 0): ?>
                                    <span class="badge badge-primary"><?= $pending_requests ?> νέα</span>
                                <?php endif; ?>
                            </div>
                        </a>
                        
                        <a href="<?= BASE_URL ?>/schools/subscriptions.php" class="quick-link">
                            <i class="fas fa-credit-card"></i>
                            <div>
                                <h4>Συνδρομές</h4>
                                <p>Διαχείριση και ανανέωση συνδρομών</p>
                            </div>
                        </a>
                        
                        <a href="<?= BASE_URL ?>/schools/statistics.php" class="quick-link">
                            <i class="fas fa-chart-line"></i>
                            <div>
                                <h4>Στατιστικά</h4>
                                <p>Αναλυτικά στατιστικά επιδόσεων μαθητών</p>
                            </div>
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <button type="submit" name="update_profile" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Αποθήκευση Αλλαγών
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<$load_map_js = true; // για τη φόρτωση του Google Maps API
?>

<script>
// Βασικές μεταβλητές για χρήση στο JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded and DOM ready');
});
</script>

<?php require_once '../includes/footer.php'; ?>