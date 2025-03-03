<?php
/**
 * AADE Integration Installation Script
 * This script installs and configures AADE integration components
 * 
 * @package DriveTest
 * @file install/aade_integration.php
 */

// Φόρτωση κύριου αρχείου ρυθμίσεων
$configPath = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configPath)) {
    die("Το αρχείο config.php δεν βρέθηκε. Βεβαιωθείτε ότι εκτελείτε το script από τον κατάλογο install/.\n");
}

// Φόρτωση της τρέχουσας διαμόρφωσης
$config = include($configPath);

echo "====================================\n";
echo "Εγκατάσταση Ενσωμάτωσης ΑΑΔΕ\n";
echo "====================================\n\n";

// Δημιουργία των απαραίτητων καταλόγων
echo "Δημιουργία απαραίτητων καταλόγων...\n";

$directories = [
    dirname(__DIR__) . '/classes',
    dirname(__DIR__) . '/api',
    dirname(__DIR__) . '/logs',
    dirname(__DIR__) . '/assets/js',
    dirname(__DIR__) . '/assets/css',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Δημιουργήθηκε ο κατάλογος: $dir\n";
        } else {
            echo "✗ Αποτυχία δημιουργίας καταλόγου: $dir\n";
        }
    } else {
        echo "- Ο κατάλογος υπάρχει ήδη: $dir\n";
    }
}

// Καταγραφή διαπιστευτηρίων ΑΑΔΕ
echo "\nΡύθμιση διαπιστευτηρίων ΑΑΔΕ\n";
echo "------------------------------------\n";

$username = readline("Εισάγετε το όνομα χρήστη ΑΑΔΕ (αφήστε κενό για 'XXXXXXXXXXXXXXXXXXXXXXXXX'): ");
$password = readline("Εισάγετε τον κωδικό πρόσβασης ΑΑΔΕ (αφήστε κενό για 'YYYYYYYYYYYYYYYYYYYYYYYYYY'): ");

$username = !empty($username) ? $username : 'XXXXXXXXXXXXXXXXXXXXXXXXX';
$password = !empty($password) ? $password : 'YYYYYYYYYYYYYYYYYYYYYYYYYY';

// Δημιουργία του αρχείου διαμόρφωσης ΑΑΔΕ
echo "\nΔημιουργία αρχείου διαμόρφωσης ΑΑΔΕ...\n";

$aadeConfigContent = <<<EOT
<?php
/**
 * AADE Integration Configuration
 * Configuration settings for AADE API integration
 * 
 * @package DriveTest
 */

// Ρυθμίσεις για την ενσωμάτωση της ΑΑΔΕ
\$config['aade_integration'] = [
    // SOAP API Endpoint
    'endpoint' => 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2?WSDL',
    
    // Διαπιστευτήρια για το API της ΑΑΔΕ
    'username' => '$username', // Αντικαταστήστε με το πραγματικό username
    'password' => '$password', // Αντικαταστήστε με το πραγματικό password
    
    // Διαδρομή για το αρχείο καταγραφής
    'log_path' => BASE_PATH . '/logs/aade_api.log',
    
    // Ενεργοποίηση/απενεργοποίηση της λειτουργίας ΑΑΔΕ
    'enabled' => true,
    
    // Χρόνος λήξης αποθηκευμένων δεδομένων (σε δευτερόλεπτα)
    'cache_expiry' => 86400, // 24 ώρες
    
    // Ρυθμίσεις για τα απαιτούμενα πεδία
    'required_fields' => [
        'schools' => ['tax_id', 'name', 'address', 'street_number', 'postal_code', 'city'],
    ],
];

// Για ευκολία πρόσβασης, ορίζουμε και τα διαπιστευτήρια ως ξεχωριστές μεταβλητές
\$config['aade_username'] = \$config['aade_integration']['username'];
\$config['aade_password'] = \$config['aade_integration']['password'];
EOT;

$aadeConfigPath = dirname(__DIR__) . '/config/aade_config.php';
if (file_put_contents($aadeConfigPath, $aadeConfigContent)) {
    echo "✓ Το αρχείο διαμόρφωσης δημιουργήθηκε: $aadeConfigPath\n";
} else {
    echo "✗ Αποτυχία δημιουργίας αρχείου διαμόρφωσης: $aadeConfigPath\n";
}

// Δημιουργία της κλάσης AADEIntegration
echo "\nΔημιουργία κλάσης AADEIntegration...\n";

$aadeClassContent = <<<'EOT'
<?php
/**
 * AADE Integration Class
 * 
 * Provides functionality to interact with the Greek Tax Authority (AADE) SOAP API
 * for validating VAT numbers and fetching company information.
 * 
 * @package DriveTest
 */

class AADEIntegration {
    /** @var string API endpoint URL */
    private $endpoint = 'https://www1.gsis.gr/wsaade/RgWsPublic2/RgWsPublic2?WSDL';
    
    /** @var string API username */
    private $username;
    
    /** @var string API password */
    private $password;
    
    /** @var SoapClient SOAP client instance */
    private $client;
    
    /** @var string Path to log API interactions */
    private $logPath;
    
    /**
     * Constructor
     * 
     * @param string $username API username
     * @param string $password API password
     * @param string $logPath Optional path to log file
     */
    public function __construct($username, $password, $logPath = null) {
        $this->username = $username;
        $this->password = $password;
        $this->logPath = $logPath ?: dirname(__DIR__) . '/logs/aade_api.log';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname($this->logPath))) {
            mkdir(dirname($this->logPath), 0755, true);
        }
        
        try {
            $this->initClient();
        } catch (Exception $e) {
            $this->logError('Failed to initialize SOAP client: ' . $e->getMessage());
            throw new Exception('Failed to connect to AADE API: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize the SOAP client
     */
    private function initClient() {
        $options = [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE
        ];
        
        $this->client = new SoapClient($this->endpoint, $options);
    }
    
    /**
     * Check if a VAT number (AFM) is valid and fetch company details
     * 
     * @param string $vatNumber The VAT number (AFM) to validate
     * @param string $asOnDate Optional date format YYYY-MM-DD
     * @return array Company information if valid
     * @throws Exception If VAT number is invalid or service error
     */
    public function getCompanyInfo($vatNumber, $asOnDate = null) {
        // Validate AFM format (9 digits)
        if (!preg_match('/^\d{9}$/', $vatNumber)) {
            throw new Exception('Invalid VAT number format. Must be 9 digits.');
        }
        
        try {
            $requestXml = $this->buildRequestXml($vatNumber, $asOnDate);
            $this->logRequest($requestXml);
            
            $response = $this->client->rgWsPublic2AfmMethod([
                'INPUT_REC' => ['afm' => $vatNumber, 'asOnDate' => $asOnDate]
            ]);
            
            $responseData = $response->result;
            $this->logResponse(print_r($responseData, true));
            
            // Check for error in response
            if (isset($responseData->error) && $responseData->error) {
                throw new Exception('AADE Error: ' . $responseData->errorMessage);
            }
            
            // Process and return company information
            return $this->processResponse($responseData);
            
        } catch (SoapFault $fault) {
            $this->logError('SOAP Fault: ' . $fault->getMessage());
            throw new Exception('AADE Service Error: ' . $fault->getMessage());
        } catch (Exception $e) {
            $this->logError('Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check AADE API version info
     * 
     * @return string Version information
     */
    public function getVersionInfo() {
        try {
            $response = $this->client->rgWsPublic2VersionInfo();
            return $response->result;
        } catch (SoapFault $fault) {
            $this->logError('SOAP Fault in version check: ' . $fault->getMessage());
            throw new Exception('AADE Service Error: ' . $fault->getMessage());
        }
    }
    
    /**
     * Build XML request for the AADE API
     * 
     * @param string $vatNumber The VAT number
     * @param string|null $asOnDate Optional date
     * @return string XML request
     */
    private function buildRequestXml($vatNumber, $asOnDate = null) {
        $xml = '<RgWsPublic2AfmMethod_WithOUTAsOnDate_Request>';
        $xml .= $this->username . ' ' . $this->password;
        $xml .= $vatNumber;
        
        if ($asOnDate) {
            $xml .= $asOnDate;
        }
        
        $xml .= '</RgWsPublic2AfmMethod_WithOUTAsOnDate_Request>';
        
        return $xml;
    }
    
    /**
     * Process and format the AADE response
     * 
     * @param object $response The SOAP response object
     * @return array Formatted company information
     */
    private function processResponse($response) {
        // Convert response to a more usable format
        return [
            'afm' => $response->afm ?? '',
            'name' => ($response->legalName ?? '') ?: ($response->firmName ?? ''),
            'commercialName' => $response->commercialTitle ?? '',
            'legalForm' => $response->legalStatusDesc ?? '',
            'address' => [
                'street' => $response->postalAddress ?? '',
                'streetNumber' => $response->postalAddressNo ?? '',
                'postalCode' => $response->postalZipCode ?? '',
                'city' => $response->postalAreaDescription ?? '',
            ],
            'status' => [
                'description' => $response->registrationDesc ?? '',
                'isActive' => (isset($response->registrationFlag) && $response->registrationFlag == 1)
            ],
            'activities' => [
                [
                    'code' => $response->firmActCode ?? '',
                    'description' => $response->firmActDescr ?? '',
                    'isPrimary' => (isset($response->firmActKindDesc) && $response->firmActKindDesc == 'ΚΥΡΙΑ')
                ]
            ],
            'registrationDate' => $response->registDate ?? '',
            'deactivationDate' => $response->stopDate ?? '',
            'raw' => $response // Keep the raw response for debugging
        ];
    }
    
    /**
     * Log API request
     * 
     * @param string $data Request data
     */
    private function logRequest($data) {
        $this->log('REQUEST: ' . $data);
    }
    
    /**
     * Log API response
     * 
     * @param string $data Response data
     */
    private function logResponse($data) {
        $this->log('RESPONSE: ' . $data);
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     */
    private function logError($message) {
        $this->log('ERROR: ' . $message);
    }
    
    /**
     * Write log to file
     * 
     * @param string $message Message to log
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($this->logPath, $logMessage, FILE_APPEND);
    }
}
EOT;

$aadeClassPath = dirname(__DIR__) . '/classes/AADEIntegration.php';
if (file_put_contents($aadeClassPath, $aadeClassContent)) {
    echo "✓ Η κλάση AADEIntegration δημιουργήθηκε: $aadeClassPath\n";
} else {
    echo "✗ Αποτυχία δημιουργίας κλάσης AADEIntegration: $aadeClassPath\n";
}

// Δημιουργία του API endpoint
echo "\nΔημιουργία API endpoint...\n";

$apiEndpointContent = <<<'EOT'
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
EOT;

$apiEndpointPath = dirname(__DIR__) . '/api/aade_api.php';
if (file_put_contents($apiEndpointPath, $apiEndpointContent)) {
    echo "✓ Το API endpoint δημιουργήθηκε: $apiEndpointPath\n";
} else {
    echo "✗ Αποτυχία δημιουργίας API endpoint: $apiEndpointPath\n";
}

// Δημιουργία του JavaScript αρχείου για το frontend
echo "\nΔημιουργία JavaScript αρχείου για το frontend...\n";

$jsContent = <<<'EOT'
/**
 * AADE Integration Client-side Script
 * Handles client-side functionality for AADE tax ID validation
 * 
 * @package DriveTest
 * @file assets/js/aade-integration.js
 */

// Ορισμός της βασικής διεύθυνσης (URL) της εφαρμογής
const baseUrl = window.location.origin + '/drivetest';

document.addEventListener('DOMContentLoaded', function() {
    // Έλεγχος για το κουμπί ΑΑΔΕ
    const aadeButtons = document.querySelectorAll('.aade-button');
    
    if (aadeButtons.length > 0) {
        aadeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const taxIdField = document.getElementById('tax_id');
                
                if (!taxIdField || !taxIdField.value.trim()) {
                    showMessage('error', 'Παρακαλώ εισάγετε έγκυρο ΑΦΜ πρώτα.');
                    return;
                }
                
                const taxId = taxIdField.value.trim();
                fetchCompanyInfo(taxId);
            });
        });
    }
    
    // Προσθήκη επικύρωσης ΑΦΜ στη φόρμα εγγραφής σχολής
    const taxIdInput = document.getElementById('tax_id');
    if (taxIdInput) {
        taxIdInput.addEventListener('blur', function() {
            validateTaxId(this.value.trim());
        });
    }
});

/**
 * Επικύρωση ΑΦΜ με βάση τον αλγόριθμο
 * 
 * @param {string} taxId ΑΦΜ προς επικύρωση
 * @returns {boolean} Εάν το ΑΦΜ είναι έγκυρο
 */
function validateTaxId(taxId) {
    // Έλεγχος μορφής (9 ψηφία)
    if (!/^\d{9}$/.test(taxId)) {
        if (taxId) {
            showFieldError('tax_id', 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία.');
        }
        return false;
    }
    
    // Αλγόριθμος επικύρωσης ΑΦΜ
    let sum = 0;
    for (let i = 0; i < 8; i++) {
        sum += parseInt(taxId.charAt(i)) * Math.pow(2, 8 - i);
    }
    
    let checkDigit = sum % 11;
    if (checkDigit > 9) {
        checkDigit = 0;
    }
    
    if (checkDigit === parseInt(taxId.charAt(8))) {
        clearFieldError('tax_id');
        return true;
    } else {
        showFieldError('tax_id', 'Μη έγκυρο ΑΦΜ. Παρακαλώ ελέγξτε τα ψηφία.');
        return false;
    }
}

/**
 * Ανάκτηση πληροφοριών επιχείρησης από την ΑΑΔΕ
 * 
 * @param {string} taxId ΑΦΜ της επιχείρησης
 */
function fetchCompanyInfo(taxId) {
    // Έλεγχος εάν το ΑΦΜ είναι έγκυρο
    if (!validateTaxId(taxId)) {
        return;
    }
    
    // Εμφάνιση φόρτωσης
    const loader = showLoader();
    
    // Αποστολή αιτήματος στον server
    fetch(`${baseUrl}/api/aade_api.php?action=info&afm=${taxId}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'Σφάλμα κατά την επικοινωνία με την ΑΑΔΕ.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Συμπλήρωση των πεδίων της φόρμας με τα δεδομένα από την ΑΑΔΕ
                populateFormFields(data.data);
                showMessage('success', 'Τα στοιχεία ανακτήθηκαν επιτυχώς από την ΑΑΔΕ.');
            } else {
                showMessage('error', data.error || 'Άγνωστο σφάλμα κατά την ανάκτηση δεδομένων.');
            }
        })
        .catch(error => {
            showMessage('error', error.message);
        })
        .finally(() => {
            // Απόκρυψη φόρτωσης
            hideLoader(loader);
        });
}

/**
 * Ενημέρωση στοιχείων σχολής από την ΑΑΔΕ
 * 
 * @param {number} schoolId ID της σχολής
 */
function updateSchoolInfo(schoolId) {
    // Εμφάνιση φόρτωσης
    const loader = showLoader();
    
    // Αποστολή αιτήματος στον server
    fetch(`${baseUrl}/api/aade_api.php?action=update_school&school_id=${schoolId}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || 'Σφάλμα κατά την επικοινωνία με την ΑΑΔΕ.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Ανανέωση της σελίδας για εμφάνιση των ενημερωμένων στοιχείων
                showMessage('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showMessage('error', data.error || 'Άγνωστο σφάλμα κατά την ενημέρωση στοιχείων.');
            }
        })
        .catch(error => {
            showMessage('error', error.message);
        })
        .finally(() => {
            // Απόκρυψη φόρτωσης
            hideLoader(loader);
        });
}

/**
 * Συμπλήρωση των πεδίων της φόρμας με τα δεδομένα από την ΑΑΔΕ
 * 
 * @param {object} data Δεδομένα επιχείρησης
 */
function populateFormFields(data) {
    // Συμπλήρωση του ονόματος της σχολής
    const schoolNameField = document.getElementById('school_name');
    if (schoolNameField) {
        schoolNameField.value = data.name;
    }
    
    // Συμπλήρωση της διεύθυνσης
    const addressField = document.getElementById('address');
    if (addressField) {
        addressField.value = data.address.street;
    }
    
    // Συμπλήρωση του αριθμού
    const streetNumberField = document.getElementById('street_number');
    if (streetNumberField) {
        streetNumberField.value = data.address.streetNumber;
    }
    
    // Συμπλήρωση του ταχυδρομικού κώδικα
    const postalCodeField = document.getElementById('postal_code');
    if (postalCodeField) {
        postalCodeField.value = data.address.postalCode;
    }
    
    // Συμπλήρωση της πόλης
    const cityField = document.getElementById('city');
    if (cityField) {
        cityField.value = data.address.city;
    }
    
    // Συμπλήρωση του υπεύθυνου (αν υπάρχει πεδίο)
    const responsiblePersonField = document.getElementById('responsible_person');
    if (responsiblePersonField && responsiblePersonField.value === '') {
        // Προτείνουμε το όνομα της εταιρείας ως υπεύθυνο εάν δεν έχει οριστεί
        responsiblePersonField.value = data.name;
    }
    
    // Ενημέρωση του label της νομικής μορφής αν υπάρχει
    const legalFormLabel = document.getElementById('legal_form_label');
    if (legalFormLabel) {
        legalFormLabel.textContent = data.legalForm || 'Μη διαθέσιμο';
    }
    
    // Αποθήκευση των δεδομένων στο session storage για μελλοντική χρήση
    sessionStorage.setItem('aadeCompanyData', JSON.stringify(data));
    
    // Εμφάνιση επιπλέον πληροφοριών στο UI
    showCompanyInfoPanel(data);
}

/**
 * Εμφάνιση πληροφοριών επιχείρησης σε πάνελ
 * 
 * @param {object} data Δεδομένα επιχείρησης
 */
function showCompanyInfoPanel(data) {
    // Έλεγχος αν υπάρχει ή δημιουργία του πάνελ
    let infoPanel = document.getElementById('aade_info_panel');
    
    if (!infoPanel) {
        infoPanel = document.createElement('div');
        infoPanel.id = 'aade_info_panel';
        infoPanel.className = 'aade-info-panel';
        
        // Προσθήκη στη σελίδα μετά το πεδίο ΑΦΜ
        const taxIdField = document.getElementById('tax_id');
        if (taxIdField && taxIdField.parentNode) {
            taxIdField.parentNode.insertAdjacentElement('afterend', infoPanel);
        }
    }
    
    // Δημιουργία περιεχομένου πάνελ
    let statusClass = data.status.isActive ? 'active-status' : 'inactive-status';
    let statusText = data.status.isActive ? 'Ενεργή Επιχείρηση' : 'Ανενεργή Επιχείρηση';
    
    infoPanel.innerHTML = `
        <div class="aade-header">
            <h4>Στοιχεία από ΑΑΔΕ</h4>
            <span class="status-badge ${statusClass}">${statusText}</span>
        </div>
        <div class="aade-body">
            <p><strong>Επωνυμία:</strong> ${data.name}</p>
            <p><strong>Διεύθυνση:</strong> ${data.address.street} ${data.address.streetNumber}, ${data.address.postalCode} ${data.address.city}</p>
            <p><strong>Νομική Μορφή:</strong> ${data.legalForm || 'Μη διαθέσιμο'}</p>
            <p><strong>Ημ. Έναρξης:</strong> ${formatDate(data.registrationDate)}</p>
            ${data.deactivationDate ? `<p><strong>Ημ. Διακοπής:</strong> ${formatDate(data.deactivationDate)}</p>` : ''}
            ${data.activities && data.activities.length > 0 ? `
                <p><strong>Κύρια Δραστηριότητα:</strong> ${data.activities[0].code} - ${data.activities[0].description}</p>
            ` : ''}
        </div>
    `;
    
    // Εμφάνιση του πάνελ
    infoPanel.style.display = 'block';
}

/**
 * Μορφοποίηση ημερομηνίας σε ελληνική μορφή
 * 
 * @param {string} dateStr Συμβολοσειρά ημερομηνίας
 * @returns {string} Μορφοποιημένη ημερομηνία
 */
function formatDate(dateStr) {
    if (!dateStr) return 'Μη διαθέσιμο';
    
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr; // Επιστροφή ως έχει εάν δεν είναι έγκυρη ημερομηνία
    
    return date.toLocaleDateString('el-GR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Εμφάνιση μηνύματος στο χρήστη
 * 
 * @param {string} type Τύπος μηνύματος ('success', 'error', 'warning', 'info')
 * @param {string} message Κείμενο μηνύματος
 */
function showMessage(type, message) {
    // Έλεγχος αν υπάρχει container μηνυμάτων
    let messagesContainer = document.querySelector('.messages-container');
    
    if (!messagesContainer) {
        messagesContainer = document.createElement('div');
        messagesContainer.className = 'messages-container';
        document.body.insertBefore(messagesContainer, document.body.firstChild);
    }
    
    // Δημιουργία του στοιχείου μηνύματος
    const messageElement = document.createElement('div');
    messageElement.className = `alert alert-${type} alert-dismissible fade show`;
    messageElement.role = 'alert';
    
    messageElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Προσθήκη στο container
    messagesContainer.appendChild(messageElement);
    
    // Αυτόματη απόκρυψη μετά από 5 δευτερόλεπτα
    setTimeout(() => {
        if (messageElement.parentNode) {
            messageElement.remove();
        }
    }, 5000);
}

/**
 * Εμφάνιση σφάλματος στο πεδίο φόρμας
 * 
 * @param {string} fieldId ID του πεδίου
 * @param {string} errorMessage Μήνυμα σφάλματος
 */
function showFieldError(fieldId, errorMessage) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Προσθήκη κλάσης σφάλματος στο πεδίο
    field.classList.add('is-invalid');
    
    // Έλεγχος αν υπάρχει ήδη στοιχείο μηνύματος σφάλματος
    let errorElement = field.nextElementSibling;
    if (!errorElement || !errorElement.classList.contains('invalid-feedback')) {
        errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        field.parentNode.insertBefore(errorElement, field.nextSibling);
    }
    
    errorElement.textContent = errorMessage;
    errorElement.style.display = 'block';
}

/**
 * Καθαρισμός σφάλματος πεδίου
 * 
 * @param {string} fieldId ID του πεδίου
 */
function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Αφαίρεση κλάσης σφάλματος
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    
    // Αφαίρεση μηνύματος σφάλματος
    const errorElement = field.nextElementSibling;
    if (errorElement && errorElement.classList.contains('invalid-feedback')) {
        errorElement.style.display = 'none';
    }
}

/**
 * Εμφάνιση ένδειξης φόρτωσης
 * 
 * @returns {HTMLElement} Το στοιχείο φόρτωσης
 */
function showLoader() {
    const loader = document.createElement('div');
    loader.className = 'loader-overlay';
    loader.innerHTML = '<div class="loader-spinner"><i class="fas fa-circle-notch fa-spin"></i></div>';
    document.body.appendChild(loader);
    return loader;
}

/**
 * Απόκρυψη ένδειξης φόρτωσης
 * 
 * @param {HTMLElement} loader Το στοιχείο φόρτωσης
 */
function hideLoader(loader) {
    if (loader && loader.parentNode) {
        loader.parentNode.removeChild(loader);
    }
}
EOT;

$jsFilePath = dirname(__DIR__) . '/assets/js/aade-integration.js';
if (file_put_contents($jsFilePath, $jsContent)) {
    echo "✓ Το JavaScript αρχείο δημιουργήθηκε: $jsFilePath\n";
} else {
    echo "✗ Αποτυχία δημιουργίας JavaScript αρχείου: $jsFilePath\n";
}

// Δημιουργία του CSS αρχείου
echo "\nΔημιουργία CSS αρχείου...\n";

$cssContent = <<<'EOT'
/**
 * AADE Integration Styles
 * CSS styles for AADE integration components
 * 
 * @package DriveTest
 * @file assets/css/aade-integration.css
 */

/* Γενικά στυλ για τη λειτουργία ΑΑΔΕ */
.aade-button {
    background-color: #4285F4;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s;
}

.aade-button:hover {
    background-color: #3367D6;
}

.aade-button:disabled {
    background-color: #A9A9A9;
    cursor: not-allowed;
}

.aade-button i {
    margin-right: 6px;
}

/* Στυλ για το πάνελ πληροφοριών ΑΑΔΕ */
.aade-info-panel {
    background-color: #f8f9fa;
    border-radius: 8px;
    border-left: 5px solid #4285F4;
    margin: 15px 0;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    display: none; /* Αρχικά κρυμμένο */
}

.aade-header {
    background-color: #e9ecef;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.aade-header h4 {
    margin: 0;
    color: #333;
    font-size: 16px;
    font-weight: bold;
}

.aade-body {
    padding: 15px;
}

.aade-body p {
    margin: 6px 0;
    font-size: 14px;
}

/* Στυλ για τις ετικέτες κατάστασης */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.active-status {
    background-color: #28a745;
}

.inactive-status {
    background-color: #dc3545;
}

/* Στυλ για τους δείκτες φόρτωσης */
.loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loader-spinner {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.loader-spinner i {
    font-size: 32px;
    color: #4285F4;
}

/* Στυλ για τα μηνύματα */
.messages-container {
    position: fixed;
    top: 10px;
    right: 10px;
    width: 300px;
    z-index: 9998;
    max-height: 80vh;
    overflow-y: auto;
}

.messages-container .alert {
    margin-bottom: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Στυλ για τα πεδία φόρμας */
.is-invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.is-valid {
    border-color: #28a745;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: .25rem;
    font-size: 80%;
    color: #dc3545;
}

/* Προσαρμογή για μικρές οθόνες */
@media (max-width: 576px) {
    .aade-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .status-badge {
        margin-top: 5px;
    }
    
    .messages-container {
        width: calc(100% - 20px);
    }
}
EOT;

$cssFilePath = dirname(__DIR__) . '/assets/css/aade-integration.css';
if (file_put_contents($cssFilePath, $cssContent)) {
    echo "✓ Το CSS αρχείο δημιουργήθηκε: $cssFilePath\n";
} else {
    echo "✗ Αποτυχία δημιουργίας CSS αρχείου: $cssFilePath\n";
}

// Ενημέρωση του config.php για να συμπεριλάβει τη διαμόρφωση ΑΑΔΕ
echo "\nΕνημέρωση του κύριου αρχείου διαμόρφωσης...\n";

$configContent = file_get_contents($configPath);
if ($configContent !== false) {
    // Έλεγχος αν υπάρχει ήδη η γραμμή require_once για το aade_config.php
    if (strpos($configContent, "aade_config.php") === false) {
        // Προσθήκη της γραμμής στο config.php
        $requireLine = "\n// Φόρτωση ρυθμίσεων ενσωμάτωσης ΑΑΔΕ\nrequire_once __DIR__ . '/aade_config.php';\n";
        
        // Εύρεση του σημείου πριν από το "return $config;"
        $returnPos = strrpos($configContent, "return");
        if ($returnPos !== false) {
            $newConfigContent = substr($configContent, 0, $returnPos) . $requireLine . substr($configContent, $returnPos);
            
            if (file_put_contents($configPath, $newConfigContent)) {
                echo "✓ Το κύριο αρχείο διαμόρφωσης ενημερώθηκε επιτυχώς.\n";
            } else {
                echo "✗ Αποτυχία ενημέρωσης κύριου αρχείου διαμόρφωσης.\n";
            }
        } else {
            echo "✗ Δεν ήταν δυνατή η ανάγνωση του αρχείου header.php.\n";
}

// Βήματα ενσωμάτωσης στη φόρμα εγγραφής σχολής
echo "\nΕνημέρωση της φόρμας εγγραφής σχολής...\n";

$registerSchoolPath = dirname(__DIR__) . '/templates/form_register_school.php';
if (file_exists($registerSchoolPath)) {
    $registerContent = file_get_contents($registerSchoolPath);
    
    if ($registerContent !== false) {
        // Έλεγχος αν υπάρχει ήδη το κουμπί ΑΑΔΕ
        if (strpos($registerContent, "aade-button") === false) {
            // Αναζήτηση της γραμμής με το πεδίο ΑΦΜ
            $taxIdPos = strpos($registerContent, 'input type="text" name="tax_id"');
            if ($taxIdPos !== false) {
                // Αναζήτηση του κλεισίματος της γραμμής
                $lineEndPos = strpos($registerContent, '>', $taxIdPos) + 1;
                
                // Προσθήκη του κουμπιού ΑΑΔΕ μετά το input
                $aadeButtonHtml = <<<'HTML'

        <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
            <button type="button" class="aade-button" onclick="fetchCompanyInfo(document.getElementById('tax_id').value)">
                <i class="fas fa-sync-alt"></i> Ανάκτηση από ΑΑΔΕ
            </button>
            <small class="form-text">Ανάκτηση στοιχείων από ΑΑΔΕ με βάση το ΑΦΜ</small>
        </div>
HTML;
                $newRegisterContent = substr($registerContent, 0, $lineEndPos) . $aadeButtonHtml . substr($registerContent, $lineEndPos);
                
                if (file_put_contents($registerSchoolPath, $newRegisterContent)) {
                    echo "✓ Η φόρμα εγγραφής σχολής ενημερώθηκε επιτυχώς.\n";
                } else {
                    echo "✗ Αποτυχία ενημέρωσης φόρμας εγγραφής σχολής.\n";
                }
            } else {
                echo "✗ Δεν ήταν δυνατή η εύρεση του πεδίου ΑΦΜ στη φόρμα εγγραφής σχολής.\n";
            }
        } else {
            echo "- Η φόρμα εγγραφής σχολής περιέχει ήδη το κουμπί ΑΑΔΕ.\n";
        }
    } else {
        echo "✗ Δεν ήταν δυνατή η ανάγνωση της φόρμας εγγραφής σχολής.\n";
    }
} else {
    echo "! Το αρχείο φόρμας εγγραφής σχολής δεν βρέθηκε.\n";
}

// Παρόμοια ενημέρωση για το προφίλ σχολής
echo "\nΕνημέρωση του προφίλ σχολής...\n";

$schoolProfilePath = dirname(__DIR__) . '/schools/school_profile.php';
if (file_exists($schoolProfilePath)) {
    $profileContent = file_get_contents($schoolProfilePath);
    
    if ($profileContent !== false) {
        // Έλεγχος αν υπάρχει ήδη το κουμπί ΑΑΔΕ
        if (strpos($profileContent, "aade-button") === false) {
            // Αναζήτηση του πεδίου ΑΦΜ στο προφίλ σχολής
            $taxIdPos = strpos($profileContent, 'id="tax_id"');
            if ($taxIdPos !== false) {
                // Αναζήτηση του κλεισίματος του div που περιέχει το πεδίο
                $divStartPos = strrpos(substr($profileContent, 0, $taxIdPos), '<div class="form-group">');
                $divEndPos = strpos($profileContent, '</div>', $taxIdPos) + 6;
                
                // Δημιουργία του νέου div με το κουμπί ΑΑΔΕ
                $aadeButtonHtml = <<<'HTML'
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="aade-button" onclick="fetchCompanyInfo(document.getElementById('tax_id').value)">
                    <i class="fas fa-sync-alt"></i> Ανάκτηση από ΑΑΔΕ
                </button>
                <small class="form-text">Ανάκτηση στοιχείων από ΑΑΔΕ με βάση το ΑΦΜ</small>
            </div>
HTML;
                $newProfileContent = substr($profileContent, 0, $divEndPos) . $aadeButtonHtml . substr($profileContent, $divEndPos);
                
                if (file_put_contents($schoolProfilePath, $newProfileContent)) {
                    echo "✓ Το προφίλ σχολής ενημερώθηκε επιτυχώς.\n";
                } else {
                    echo "✗ Αποτυχία ενημέρωσης προφίλ σχολής.\n";
                }
            } else {
                echo "✗ Δεν ήταν δυνατή η εύρεση του πεδίου ΑΦΜ στο προφίλ σχολής.\n";
            }
        } else {
            echo "- Το προφίλ σχολής περιέχει ήδη το κουμπί ΑΑΔΕ.\n";
        }
    } else {
        echo "✗ Δεν ήταν δυνατή η ανάγνωση του προφίλ σχολής.\n";
    }
} else {
    echo "! Το αρχείο προφίλ σχολής δεν βρέθηκε.\n";
}

// Ολοκλήρωση
echo "\n====================================\n";
echo "Η εγκατάσταση της ενσωμάτωσης ΑΑΔΕ ολοκληρώθηκε επιτυχώς!\n";
echo "====================================\n\n";
echo "Μην ξεχάσετε να ενημερώσετε τα διαπιστευτήρια της ΑΑΔΕ στο αρχείο config/aade_config.php με τα πραγματικά σας στοιχεία.\n";
echo "Μπορείτε να αποκτήσετε διαπιστευτήρια για το API από την ΑΑΔΕ στη διεύθυνση: https://www.aade.gr/epiheiriseis/forologikes-ypiresies/mitroo/anazitisi-basikon-stoiheion-mitrooy-epiheiriseon\n\n";
echo "η εύρεση του σημείου επιστροφής στο αρχείο διαμόρφωσης.\n";
    } else {
        echo "- Το αρχείο config.php περιέχει ήδη τις ρυθμίσεις ΑΑΔΕ.\n";
    }
} else {
    echo "✗ Δεν ήταν δυνατή η ανάγνωση του αρχείου config.php.\n";
}

// Ενημέρωση του αρχείου header.php για να συμπεριλάβει τα JS και CSS αρχεία
echo "\nΕνημέρωση του αρχείου header.php...\n";

$headerPath = dirname(__DIR__) . '/includes/header.php';
$headerContent = file_get_contents($headerPath);

if ($headerContent !== false) {
    // Έλεγχος αν υπάρχουν ήδη οι γραμμές για τα αρχεία JS και CSS
    if (strpos($headerContent, "aade-integration.css") === false && strpos($headerContent, "aade-integration.js") === false) {
        // Εύρεση του head tag για εισαγωγή των CSS
        $headEndPos = strpos($headerContent, "</head>");
        if ($headEndPos !== false) {
            $cssLine = "    <link rel=\"stylesheet\" href=\"<?= BASE_URL ?>/assets/css/aade-integration.css\">\n";
            $newHeaderContent = substr($headerContent, 0, $headEndPos) . $cssLine . substr($headerContent, $headEndPos);
            
            // Εύρεση του σημείου πριν από το </body> για εισαγωγή των JS
            $bodyEndPos = strrpos($newHeaderContent, "</body>");
            if ($bodyEndPos !== false) {
                $jsLine = "    <script src=\"<?= BASE_URL ?>/assets/js/aade-integration.js\"></script>\n";
                $newHeaderContent = substr($newHeaderContent, 0, $bodyEndPos) . $jsLine . substr($newHeaderContent, $bodyEndPos);
                
                if (file_put_contents($headerPath, $newHeaderContent)) {
                    echo "✓ Το αρχείο header.php ενημερώθηκε επιτυχώς.\n";
                } else {
                    echo "✗ Αποτυχία ενημέρωσης αρχείου header.php.\n";
                }
            } else {
                echo "✗ Δεν ήταν δυνατή η εύρεση του tag </body> στο αρχείο header.php.\n";
            }
        } else {
            echo "✗ Δεν ήταν δυνατή η εύρεση του tag </head> στο αρχείο header.php.\n";
        }
    } else {
        echo "- Το αρχείο header.php περιέχει ήδη τα αρχεία JS και CSS της ΑΑΔΕ.\n";
    }
} else {
    echo "✗ Δεν ήταν δυνατή η ανάγνωση του αρχείου header.php.\n";
}