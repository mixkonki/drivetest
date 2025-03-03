<?php
/**
 * AADE API Endpoint
 * Handles API requests for AADE integration
 * 
 * @package DriveTest
 * @file api/aade_api.php
 */

// Ορισμός του header για JSON response
header('Content-Type: application/json');

// Φόρτωση των απαραίτητων αρχείων
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db_connection.php';
require_once dirname(__DIR__) . '/classes/AADEIntegration.php';

// Έλεγχος της μεθόδου HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Μη αποδεκτή μέθοδος. Επιτρέπονται μόνο GET και POST.']);
    exit();
}

// Έλεγχος αν η ενσωμάτωση ΑΑΔΕ είναι ενεργοποιημένη
if (!isset($config['aade_integration']['enabled']) || !$config['aade_integration']['enabled']) {
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Η υπηρεσία ΑΑΔΕ δεν είναι διαθέσιμη αυτή τη στιγμή.']);
    exit();
}

// Λήψη της ενέργειας από το αίτημα
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Έλεγχος αν έχει οριστεί ενέργεια
if (empty($action)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Δεν έχει οριστεί ενέργεια.']);
    exit();
}

// Έλεγχος διαπιστευτηρίων ΑΑΔΕ
if (empty($config['aade_username']) || empty($config['aade_password'])) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Λείπουν τα διαπιστευτήρια για την υπηρεσία ΑΑΔΕ.']);
    exit();
}

try {
    // Αρχικοποίηση του αντικειμένου ενσωμάτωσης ΑΑΔΕ
    $aadeIntegration = new AADEIntegration(
        $config['aade_username'],
        $config['aade_password'],
        $config['aade_integration']['log_path']
    );
    
    // Εκτέλεση της ενέργειας
    switch ($action) {
        case 'validate':
            validateAfm($aadeIntegration);
            break;
            
        case 'info':
            getCompanyInfo($aadeIntegration);
            break;
            
        case 'version':
            getVersionInfo($aadeIntegration);
            break;
            
        default:
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Μη έγκυρη ενέργεια.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

/**
 * Επικύρωση ΑΦΜ
 * 
 * @param AADEIntegration $aadeIntegration Αντικείμενο ενσωμάτωσης ΑΑΔΕ
 */
function validateAfm($aadeIntegration) {
    // Έλεγχος αν παρέχεται το ΑΦΜ
    if (!isset($_REQUEST['afm']) || empty($_REQUEST['afm'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Το ΑΦΜ είναι υποχρεωτικό.']);
        exit();
    }
    
    $afm = trim($_REQUEST['afm']);
    
    // Επικύρωση με βάση τον αλγόριθμο
    if (!isValidAfm($afm)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Μη έγκυρο ΑΦΜ. Ο αλγόριθμος επικύρωσης απέτυχε.']);
        exit();
    }
    
    try {
        // Επιστροφή απλής επιτυχίας χωρίς δεδομένα
        echo json_encode(['success' => true, 'afm' => $afm]);
    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Λήψη πληροφοριών επιχείρησης
 * 
 * @param AADEIntegration $aadeIntegration Αντικείμενο ενσωμάτωσης ΑΑΔΕ
 */
function getCompanyInfo($aadeIntegration) {
    // Έλεγχος αν παρέχεται το ΑΦΜ
    if (!isset($_REQUEST['afm']) || empty($_REQUEST['afm'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Το ΑΦΜ είναι υποχρεωτικό.']);
        exit();
    }
    
    $afm = trim($_REQUEST['afm']);
    $asOnDate = isset($_REQUEST['date']) ? trim($_REQUEST['date']) : null;
    
    try {
        // Λήψη πληροφοριών επιχείρησης
        $companyInfo = $aadeIntegration->getCompanyInfo($afm, $asOnDate);
        
        // Επιστροφή των αποτελεσμάτων σε μορφή JSON
        echo json_encode(['success' => true, 'data' => $companyInfo]);
    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Λήψη πληροφοριών έκδοσης του API
 * 
 * @param AADEIntegration $aadeIntegration Αντικείμενο ενσωμάτωσης ΑΑΔΕ
 */
function getVersionInfo($aadeIntegration) {
    try {
        $versionInfo = $aadeIntegration->getVersionInfo();
        
        echo json_encode(['success' => true, 'version' => $versionInfo]);
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Επικύρωση ΑΦΜ με βάση τον αλγόριθμο
 * 
 * @param string $afm ΑΦΜ προς επικύρωση
 * @return bool Αν το ΑΦΜ είναι έγκυρο
 */
function isValidAfm($afm) {
    // Έλεγχος μορφής (9 ψηφία)
    if (!preg_match('/^\d{9}$/', $afm)) {
        return false;
    }
    
    // Αλγόριθμος επικύρωσης ΑΦΜ
    $sum = 0;
    for ($i = 0; $i < 8; $i++) {
        $sum += intval($afm[$i]) * pow(2, 8 - $i);
    }
    
    $checkDigit = $sum % 11;
    if ($checkDigit > 9) {
        $checkDigit = 0;
    }
    
    return $checkDigit === intval($afm[8]);
}