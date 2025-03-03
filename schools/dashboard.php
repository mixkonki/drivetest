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
$query = "SELECT u.fullname, u.email, u.phone, u.address, u.street_number, u.postal_code, u.city, 
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
    
    // Μετατροπή των social_links από JSON σε πίνακα αν υπάρχουν
    $school['social_links'] = !empty($school['social_links']) ? json_decode($school['social_links'], true) : [];
}
$stmt->close();

// Επεξεργασία στοιχείων σχολής
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $street_number = trim($_POST['street_number']);
    $postal_code = trim($_POST['postal_code']);
    $city = trim($_POST['city']);
    $responsible_person = trim($_POST['responsible_person']);
    $website = trim($_POST['website']);
    
    // Social links
    $facebook = trim($_POST['facebook']);
    $instagram = trim($_POST['instagram']);
    $twitter = trim($_POST['twitter']);
    $linkedin = trim($_POST['linkedin']);
    
    $social_links = [];
    if (!empty($facebook)) $social_links['facebook'] = $facebook;
    if (!empty($instagram)) $social_links['instagram'] = $instagram;
    if (!empty($twitter)) $social_links['twitter'] = $twitter;
    if (!empty($linkedin)) $social_links['linkedin'] = $linkedin;
    
    $social_links_json = json_encode($social_links);
    
    // Γεωκωδικοποίηση αν άλλαξε η διεύθυνση
    $latitude = $school['latitude'];
    $longitude = $school['longitude'];
    if (($address != $school['address'] || $street_number != $school['street_number'] || 
         $city != $school['city'] || $postal_code != $school['postal_code']) && 
        !empty($address) && !empty($city)) {
        
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
    
    // Έλεγχος και επεξεργασία του λογότυπου
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
                $logo = $target_file;
            } else {
                $error = "Αποτυχία ανεβάσματος λογότυπου.";
            }
        } else {
            $error = "Μη αποδεκτός τύπος αρχείου. Επιτρέπονται μόνο εικόνες JPG, PNG και GIF.";
        }
    }
    
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
    $stmt_user->bind_param("sssssddі", $phone, $address, $street_number, $postal_code, $city, $latitude, $longitude, $user_id);
    
    if (!$stmt_user->execute()) {
        $error = "Σφάλμα κατά την ενημέρωση των στοιχείων χρήστη: " . $mysqli->error;
    } else {
        // Ενημέρωση στοιχείων σχολής
        $update_school = "UPDATE schools SET 
                         responsible_person = ?, 
                         address = ?, 
                         street_number = ?, 
                         postal_code = ?, 
                         city = ?, 
                         logo = ?, 
                         website = ?, 
                         social_links = ? 
                         WHERE id = ?";
        
        $stmt_school = $mysqli->prepare($update_school);
        $stmt_school->bind_param("ssssssssi", $responsible_person, $address, $street_number, $postal_code, $city, $logo, $website, $social_links_json, $school['school_id']);
        
        if (!$stmt_school->execute()) {
            $error = "Σφάλμα κατά την ενημέρωση των στοιχείων σχολής: " . $mysqli->error;
        } else {
            $success = "Τα στοιχεία ενημερώθηκαν επιτυχώς!";
            
            // Ανανέωση των στοιχείων στη σελίδα
            $school['phone'] = $phone;
            $school['address'] = $address;
            $school['street_number'] = $street_number;
            $school['postal_code'] = $postal_code;
            $school['city'] = $city;
            $school['latitude'] = $latitude;
            $school['longitude'] = $longitude;
            $school['responsible_person'] = $responsible_person;
            $school['logo'] = $logo;
            $school['website'] = $website;
            $school['social_links'] = $social_links;
        }
        $stmt_school->close();
    }
    $stmt_user->close();
}

// Λήψη των μαθητών της σχολής
$students_query = "SELECT id, fullname, email, subscription_status, created_at FROM users WHERE school_id = ? AND role = 'student'";
$stmt_students = $mysqli->prepare($students_query);
$stmt_students->bind_param("i", $school['school_id']);
$stmt_students->execute();
$students_result = $stmt_students->get_result();
$students = [];
while ($student = $students_result->fetch_assoc()) {
    $students[] = $student;
}
$stmt_students->close();

// Λήψη των ενεργών συνδρομών της σχολής
$subscriptions_query = "SELECT * FROM subscriptions WHERE school_id = ? ORDER BY expiry_date DESC";
$stmt_subs = $mysqli->prepare($subscriptions_query);
$stmt_subs->bind_param("i", $school['school_id']);
$stmt_subs->execute();
$subscriptions_result = $stmt_subs->get_result();
$subscriptions = [];
while ($sub = $subscriptions_result->fetch_assoc()) {
    $subscriptions[] = $sub;
}
$stmt_subs->close();

// Λήψη των διαθέσιμων κατηγοριών
$categories_query = "SELECT id, name FROM subscription_categories";
$categories_result = $mysqli->query($categories_query);
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[$category['id']] = $category['name'];
}

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<style>
    .nav-tabs {
        margin-bottom: 20px;
    }
    .nav-tabs .nav-link {
        cursor: pointer;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 5px 5px 0 0;
        background-color: #f8f8f8;
        margin-right: 5px;
    }
    .nav-tabs .nav-link.active {
        background-color: #aa3636;
        color: white;
        border-color: #aa3636;
    }
    .tab-content {
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 0 5px 5px 5px;
    }
    .tab-pane {
        display: none;
    }
    .tab-pane.active {
        display: block;
    }
    .school-logo {
        max-width: 200px;
        max-height: 200px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .student-list-table, .subscription-list-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .student-list-table th, .student-list-table td,
    .subscription-list-table th, .subscription-list-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .student-list-table th, .subscription-list-table th {
        background-color: #aa3636;
        color: white;
    }
    .student-list-table tr:nth-child(even), .subscription-list-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    .add-student-form {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 5px;
    }
    .social-links-section {
        margin-top: 15px;
    }
    .social-link-input {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .social-link-input i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }
    .subscription-info {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 5px;
        border-left: 5px solid #aa3636;
    }
    .student-actions {
        display: flex;
        gap: 5px;
    }
    .student-actions a, .student-actions button {
        background: none;
        border: none;
        cursor: pointer;
        color: #aa3636;
        text-decoration: none;
    }
    .student-actions a:hover, .student-actions button:hover {
        text-decoration: underline;
    }
    .import-students-section {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 5px;
        border-left: 5px solid #28a745;
    }
</style>

<div class="container">
    <div class="header">
        <h2>Προφίλ Σχολής - <?= htmlspecialchars($school['fullname']) ?></h2>
        <p>Διαχειριστείτε τα στοιχεία σας, τους μαθητές και τις συνδρομές σας.</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <ul class="nav-tabs">
        <li class="nav-item">
            <a class="nav-link active" data-tab="profile-tab">Προφίλ Σχολής</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-tab="students-tab">Διαχείριση Μαθητών</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-tab="subscriptions-tab">Συνδρομές</a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Tab Προφίλ Σχολής -->
        <div id="profile-tab" class="tab-pane active">
            <form action="" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Βασικά Στοιχεία</h3>
                        <div class="form-group">
                            <label>Επωνυμία Σχολής:</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($school['fullname']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($school['email']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>ΑΦΜ:</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($school['tax_id']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Υπεύθυνο Άτομο:</label>
                            <input type="text" name="responsible_person" class="form-control" value="<?= htmlspecialchars($school['responsible_person']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Αριθμός Άδειας:</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($school['license_number']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Τηλέφωνο:</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($school['phone']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Ιστοσελίδα:</label>
                            <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($school['website']) ?>">
                        </div>
                        
                        <div class="social-links-section">
                            <h4>Κοινωνικά Δίκτυα</h4>
                            <div class="social-link-input">
                                <i class="fab fa-facebook"></i>
                                <input type="url" name="facebook" class="form-control" placeholder="Προφίλ Facebook" value="<?= htmlspecialchars($school['social_links']['facebook'] ?? '') ?>">
                            </div>
                            <div class="social-link-input">
                                <i class="fab fa-instagram"></i>
                                <input type="url" name="instagram" class="form-control" placeholder="Προφίλ Instagram" value="<?= htmlspecialchars($school['social_links']['instagram'] ?? '') ?>">
                            </div>
                            <div class="social-link-input">
                                <i class="fab fa-twitter"></i>
                                <input type="url" name="twitter" class="form-control" placeholder="Προφίλ Twitter" value="<?= htmlspecialchars($school['social_links']['twitter'] ?? '') ?>">
                            </div>
                            <div class="social-link-input">
                                <i class="fab fa-linkedin"></i>
                                <input type="url" name="linkedin" class="form-control" placeholder="Προφίλ LinkedIn" value="<?= htmlspecialchars($school['social_links']['linkedin'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h3>Λογότυπο</h3>
                        <?php if (!empty($school['logo'])): ?>
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($school['logo']) ?>" alt="Λογότυπο σχολής" class="school-logo">
                        <?php else: ?>
                            <p>Δεν έχει οριστεί λογότυπο</p>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Ανέβασμα Λογότυπου:</label>
                            <input type="file" name="logo" class="form-control">
                            <small class="form-text text-muted">Επιτρέπονται αρχεία JPG, PNG και GIF έως 2MB</small>
                        </div>
                        
                        <h3>Διεύθυνση</h3>
                        <div class="form-group">
                            <label>Οδός:</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($school['address']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Αριθμός:</label>
                            <input type="text" name="street_number" class="form-control" value="<?= htmlspecialchars($school['street_number']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Ταχυδρομικός Κώδικας:</label>
                            <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($school['postal_code']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Πόλη:</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($school['city']) ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <h3>Κατηγορίες Εκπαίδευσης</h3>
                    <div class="categories-container">
                        <?php foreach ($categories as $id => $name): ?>
                            <div class="category-checkbox">
                                <input type="checkbox" id="cat_<?= $id ?>" <?= in_array($name, $school['categories']) ? 'checked' : '' ?> disabled>
                                <label for="cat_<?= $id ?>"><?= htmlspecialchars($name) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted">Για αλλαγή των κατηγοριών εκπαίδευσης, επικοινωνήστε με τον διαχειριστή.</p>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="update_profile" class="btn-primary">Ενημέρωση Προφίλ</button>
                </div>
            </form>
        </div>
        
        <!-- Tab Διαχείριση Μαθητών -->
        <div id="students-tab" class="tab-pane">
            <div class="row">
                <div class="col-md-8">
                    <h3>Λίστα Μαθητών</h3>
                    <?php if (count($students) > 0): ?>
                        <table class="student-list-table">
                            <thead>
                                <tr>
                                    <th>Ονοματεπώνυμο</th>
                                    <th>Email</th>
                                    <th>Συνδρομή</th>
                                    <th>Εγγραφή</th>
                                    <th>Ενέργειες</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['fullname']) ?></td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td><?= htmlspecialchars($student['subscription_status']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($student['created_at'])) ?></td>
                                        <td class="student-actions">
                                            <a href="<?= BASE_URL ?>/schools/student_progress.php?id=<?= $student['id'] ?>" title="Πρόοδος"><i class="fas fa-chart-line"></i></a>
                                            <a href="<?= BASE_URL ?>/schools/student_subscription.php?id=<?= $student['id'] ?>" title="Συνδρομή"><i class="fas fa-star"></i></a>
                                            <form action="<?= BASE_URL ?>/schools/remove_student.php" method="post" class="d-inline">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" title="Αφαίρεση" onclick="return confirm('Είστε σίγουροι ότι θέλετε να αφαιρέσετε αυτόν τον μαθητή;')">
                                                    <i class="fas fa-user-minus"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Δεν έχετε προσθέσει μαθητές ακόμα.</p>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <div class="add-student-form">
                        <h3>Προσθήκη Μαθητή</h3>
                        <form action="<?= BASE_URL ?>/schools/add_student.php" method="post">
                            <div class="form-group">
                                <label>Email Μαθητή:</label>
                                <input type="email" name="student_email" class="form-control" required>
                            </div>
                            <button type="submit" class="btn-primary">Προσθήκη Μαθητή</button>
                        </form>
                    </div>
                    
                    <div class="import-students-section">
                        <h3>Εισαγωγή Πολλαπλών Μαθητών</h3>
                        <form action="<?= BASE_URL ?>/schools/import_students.php" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Αρχείο CSV:</label>
                                <input type="file" name="students_csv" class="form-control" accept=".csv" required>
                                <small class="form-text text-muted">Το αρχείο πρέπει να περιέχει στήλες: email, fullname, phone (προαιρετικό)</small>
                            </div>
                            <button type="submit" class="btn-primary">Εισαγωγή από CSV</button>
                        </form>
                        <p><a href="<?= BASE_URL ?>/templates/students_template.csv" download>Κατεβάστε πρότυπο CSV</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab Συνδρομές -->
        <div id="subscriptions-tab" class="tab-pane">
            <div class="subscription-info">
                <h3>Στοιχεία Συνδρομής</h3>
                <p><strong>Τύπος Συνδρομής:</strong> <?= htmlspecialchars($school['subscription_type'] ?? 'Δεν έχει οριστεί') ?></p>
                <p><strong>Ημερομηνία Λήξης:</strong> <?= !empty($school['subscription_expiry']) ? date('d/m/Y', strtotime($school['subscription_expiry'])) : 'Δεν έχει οριστεί' ?></p>
                <p><strong>Μέγιστος Αριθμός Μαθητών:</strong> <?= htmlspecialchars($school['students_limit'] ?? '0') ?></p>
                <p><strong>Τρέχων Αριθμός Μαθητών:</strong> <?= count($students) ?></p>
                
                <a href="<?= BASE_URL ?>/subscriptions/buy.php?type=school" class="btn-primary">Αγορά/Ανανέωση Συνδρομής</a>
            </div>
            
            <h3>Ιστορικό Συνδρομών</h3>
            <?php if (count($subscriptions) > 0): ?>
                <table class="subscription-list-table">
                    <thead>
                        <tr>
                            <th>Κατηγορίες</th>
                            <th>Ημερομηνία Έναρξης</th>
                            <th>Ημερομηνία Λήξης</th>
                            <th>Κατάσταση</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $sub): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $sub_categories = json_decode($sub['categories'], true);
                                    if (is_array($sub_categories)) {
                                        foreach ($sub_categories as $cat_id) {
                                            echo isset($categories[$cat_id]) ? htmlspecialchars($categories[$cat_id]) . '<br>' : '';
                                        }
                                    } else {
                                        echo htmlspecialchars($sub['categories']);
                                    }
                                    ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($sub['created_at'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($sub['expiry_date'])) ?></td>
                                <td>
                                    <?php
                                    if ($sub['status'] === 'active') {
                                        echo '<span class="badge badge-success">Ενεργή</span>';
                                    } elseif ($sub['status'] === 'expired') {
                                        echo '<span class="badge badge-danger">Ληγμένη</span>';
                                    } else {
                                        echo '<span class="badge badge-warning">Σε εκκρεμότητα</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Δεν υπάρχουν καταχωρημένες συνδρομές.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabs = document.querySelectorAll('.nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all tab panes
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Show the selected tab pane
            const targetTab = this.getAttribute('data-tab');
            document.getElementById(targetTab).classList.add('active');
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>