<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

// Φόρτωση header
require_once '../includes/header.php';

// Προετοιμασία παραμέτρων αναζήτησης
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$city = isset($_GET['city']) ? trim($_GET['city']) : '';
$distance = isset($_GET['distance']) ? (int)$_GET['distance'] : 10; // Προεπιλογή: 10km
$latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Λήψη κατηγοριών για dropdown
$categories_query = "SELECT id, name FROM subscription_categories ORDER BY name";
$categories_result = $mysqli->query($categories_query);
$categories = [];

if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories[$cat['id']] = $cat['name'];
    }
}

// Λήψη μοναδικών πόλεων για dropdown
$cities_query = "SELECT DISTINCT city FROM schools WHERE city IS NOT NULL AND city != '' ORDER BY city";
$cities_result = $mysqli->query($cities_query);
$cities = [];

if ($cities_result) {
    while ($city_row = $cities_result->fetch_assoc()) {
        $cities[] = $city_row['city'];
    }
}

// Δημιουργία του βασικού query αναζήτησης
$query = "SELECT s.id, s.name, s.address, s.street_number, s.postal_code, s.city, 
                 s.latitude, s.longitude, s.logo, s.categories, s.website 
          FROM schools s
          WHERE 1=1";
$params = [];
$types = "";

// Προσθήκη φίλτρου ονόματος ή διεύθυνσης
if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR s.address LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Προσθήκη φίλτρου κατηγορίας
if (!empty($category)) {
    $query .= " AND JSON_CONTAINS(s.categories, ?)";
    $params[] = '"' . $category . '"';
    $types .= "s";
}

// Προσθήκη φίλτρου πόλης
if (!empty($city)) {
    $query .= " AND s.city = ?";
    $params[] = $city;
    $types .= "s";
}

// Προσθήκη φίλτρου απόστασης αν υπάρχουν συντεταγμένες
if ($latitude !== null && $longitude !== null && $distance > 0) {
    // Χρήση του θεωρήματος του Πυθαγόρα για απλουστευμένο υπολογισμό απόστασης
    // 1 μοίρα γεωγραφικού πλάτους ≈ 111.32 χλμ
    // 1 μοίρα γεωγραφικού μήκους ≈ 111.32 * συνημίτονο(γεωγραφικό πλάτος) χλμ
    $lat_distance = $distance / 111.32;
    
    // Υπολογισμός συντελεστή για το μήκος με βάση το γεωγραφικό πλάτος (σε ακτίνια)
    $lng_distance = $distance / (111.32 * cos(deg2rad($latitude)));
    
    $query .= " AND s.latitude BETWEEN ? AND ?
                AND s.longitude BETWEEN ? AND ?";
    
    $params[] = $latitude - $lat_distance;
    $params[] = $latitude + $lat_distance;
    $params[] = $longitude - $lng_distance;
    $params[] = $longitude + $lng_distance;
    $types .= "dddd";
}

// Ταξινόμηση αποτελεσμάτων
$query .= " ORDER BY s.name ASC";

// Εκτέλεση του query
$schools = [];
if (!empty($types) && !empty($params)) {
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($school = $result->fetch_assoc()) {
            // Μετατροπή των κατηγοριών από JSON σε πίνακα
            $school['categories'] = json_decode($school['categories'], true);
            
            // Υπολογισμός ακριβούς απόστασης αν υπάρχουν συντεταγμένες
            if ($latitude !== null && $longitude !== null && !empty($school['latitude']) && !empty($school['longitude'])) {
                $school['distance'] = haversineDistance($latitude, $longitude, $school['latitude'], $school['longitude']);
            } else {
                $school['distance'] = null;
            }
            
            $schools[] = $school;
        }
        
        $stmt->close();
        
        // Ταξινόμηση με βάση την απόσταση αν υπάρχουν συντεταγμένες
        if ($latitude !== null && $longitude !== null) {
            usort($schools, function($a, $b) {
                if ($a['distance'] === null) return 1;
                if ($b['distance'] === null) return -1;
                return $a['distance'] <=> $b['distance'];
            });
        }
    }
} else {
    // Αν δεν υπάρχουν φίλτρα, εμφανίζουμε τις τοπ 10 σχολές
    $query = "SELECT s.id, s.name, s.address, s.street_number, s.postal_code, s.city, 
                    s.latitude, s.longitude, s.logo, s.categories, s.website 
             FROM schools s 
             ORDER BY s.name ASC LIMIT 10";
             
    $result = $mysqli->query($query);
    
    while ($school = $result->fetch_assoc()) {
        // Μετατροπή των κατηγοριών από JSON σε πίνακα
        $school['categories'] = json_decode($school['categories'], true);
        $school['distance'] = null;
        $schools[] = $school;
    }
}

// Συνάρτηση υπολογισμού απόστασης με τον τύπο Haversine (σε χιλιόμετρα)
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Ακτίνα της Γης σε χιλιόμετρα
    
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
         
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/school-search.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<div class="container">
    <div class="search-header">
        <h1>Αναζήτηση Σχολών</h1>
        <p>Βρείτε σχολές κοντά σας ή με βάση τα κριτήρια που επιθυμείτε</p>
    </div>
    
    <div class="search-container">
        <form id="search-form" action="<?= BASE_URL ?>/public/school_search.php" method="get">
            <div class="search-filters">
                <div class="search-input">
                    <label for="search">Αναζήτηση</label>
                    <input type="text" id="search" name="search" placeholder="Επωνυμία ή διεύθυνση σχολής" value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="filter-group">
                    <label for="category">Κατηγορία</label>
                    <select id="category" name="category">
                        <option value="">Όλες οι κατηγορίες</option>
                        <?php foreach ($categories as $cat_id => $cat_name): ?>
                            <option value="<?= htmlspecialchars($cat_name) ?>" <?= $category === $cat_name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="city">Πόλη</label>
                    <select id="city" name="city">
                        <option value="">Όλες οι πόλεις</option>
                        <?php foreach ($cities as $city_name): ?>
                            <option value="<?= htmlspecialchars($city_name) ?>" <?= $city === $city_name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($city_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="location-group">
                    <label>
                        <input type="checkbox" id="use-location" <?= ($latitude !== null && $longitude !== null) ? 'checked' : '' ?>>
                        Χρήση τοποθεσίας μου
                    </label>
                    <div class="distance-slider" <?= ($latitude === null || $longitude === null) ? 'style="display:none;"' : '' ?>>
                        <label for="distance">Απόσταση: <span id="distance-value"><?= $distance ?></span> χλμ</label>
                        <input type="range" id="distance" name="distance" min="1" max="50" step="1" value="<?= $distance ?>">
                    </div>
                    <input type="hidden" id="lat" name="lat" value="<?= $latitude ?>">
                    <input type="hidden" id="lng" name="lng" value="<?= $longitude ?>">
                </div>
            </div>
            
            <div class="search-actions">
                <button type="submit" class="search-button"><i class="fas fa-search"></i> Αναζήτηση</button>
                <button type="button" id="reset-button" class="reset-button"><i class="fas fa-undo"></i> Επαναφορά</button>
            </div>
        </form>
    </div>
    
    <div class="results-info">
        <h2>Αποτελέσματα Αναζήτησης</h2>
        <p>Βρέθηκαν <?= count($schools) ?> σχολές</p>
    </div>
    
    <div class="search-results">
        <?php if (empty($schools)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>Δεν βρέθηκαν αποτελέσματα</h3>
                <p>Δοκιμάστε να αλλάξετε τα κριτήρια αναζήτησης.</p>
            </div>
        <?php else: ?>
            <?php foreach ($schools as $school): ?>
                <div class="school-card">
                    <div class="school-logo">
                        <?php if (!empty($school['logo']) && file_exists(dirname(__DIR__) . '/' . $school['logo'])): ?>
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($school['logo']) ?>" alt="<?= htmlspecialchars($school['name']) ?>">
                        <?php else: ?>
                            <div class="default-logo">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="school-info">
                        <h3><?= htmlspecialchars($school['name']) ?></h3>
                        
                        <div class="school-address">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>
                                <?= htmlspecialchars($school['address'] . ' ' . $school['street_number'] . ', ' . $school['postal_code'] . ' ' . $school['city']) ?>
                                <?php if ($school['distance'] !== null): ?>
                                    <span class="school-distance">(<?= number_format($school['distance'], 1) ?> χλμ)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($school['categories']) && is_array($school['categories'])): ?>
                            <div class="school-categories">
                                <i class="fas fa-tags"></i>
                                <span>
                                    <?php echo implode(', ', array_map('htmlspecialchars', $school['categories'])); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($school['website'])): ?>
                            <div class="school-website">
                                <i class="fas fa-globe"></i>
                                <a href="<?= htmlspecialchars($school['website']) ?>" target="_blank">
                                    <?= htmlspecialchars($school['website']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="school-actions">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                            <form action="<?= BASE_URL ?>/users/request_school.php" method="post">
                                <input type="hidden" name="school_id" value="<?= $school['id'] ?>">
                                <button type="submit" class="join-button">Αίτηση Συμμετοχής</button>
                            </form>
                        <?php elseif (!isset($_SESSION['user_id'])): ?>
                            <a href="<?= BASE_URL ?>/public/login.php" class="join-button">Σύνδεση για Αίτηση</a>
                        <?php endif; ?>
                        
                        <a href="<?= BASE_URL ?>/public/school_details.php?id=<?= $school['id'] ?>" class="details-button">Περισσότερα</a>
                        
                        <?php if (!empty($school['latitude']) && !empty($school['longitude'])): ?>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $school['latitude'] ?>,<?= $school['longitude'] ?>" target="_blank" class="directions-button">
                                <i class="fas fa-directions"></i> Οδηγίες
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const useLocationCheckbox = document.getElementById('use-location');
    const distanceSlider = document.querySelector('.distance-slider');
    const distanceValue = document.getElementById('distance-value');
    const distanceInput = document.getElementById('distance');
    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');
    const resetButton = document.getElementById('reset-button');
    
    // Ενημέρωση της τιμής απόστασης καθώς ο χρήστης μετακινεί το slider
    distanceInput.addEventListener('input', function() {
        distanceValue.textContent = this.value;
    });
    
    // Χειρισμός του checkbox για τη χρήση τοποθεσίας
    useLocationCheckbox.addEventListener('change', function() {
        if (this.checked) {
            // Αίτημα για την τοποθεσία του χρήστη
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    latInput.value = position.coords.latitude;
                    lngInput.value = position.coords.longitude;
                    distanceSlider.style.display = 'block';
                }, function(error) {
                    console.error("Error getting location:", error);
                    useLocationCheckbox.checked = false;
                    alert("Δεν ήταν δυνατή η λήψη της τοποθεσίας. Παρακαλώ ελέγξτε τις ρυθμίσεις του προγράμματος περιήγησής σας.");
                });
            } else {
                useLocationCheckbox.checked = false;
                alert("Ο browser σας δεν υποστηρίζει geolocation.");
            }
        } else {
            // Καθαρισμός των πεδίων τοποθεσίας
            latInput.value = '';
            lngInput.value = '';
            distanceSlider.style.display = 'none';
        }
    });
    
    // Χειρισμός του κουμπιού επαναφοράς
    resetButton.addEventListener('click', function() {
        document.getElementById('search').value = '';
        document.getElementById('category').selectedIndex = 0;
        document.getElementById('city').selectedIndex = 0;
        useLocationCheckbox.checked = false;
        latInput.value = '';
        lngInput.value = '';
        distanceSlider.style.display = 'none';
        distanceInput.value = 10;
        distanceValue.textContent = '10';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
"