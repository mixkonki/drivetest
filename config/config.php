<?php
// Εμφάνιση σφαλμάτων για debugging (Απενεργοποίησέ το σε production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
$config['debug'] = true;

// Ξεκινάμε το session (ελέγχουμε αν έχει ήδη ξεκινήσει)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ορισμός των σταθερών για το PATH και το URL της εφαρμογής
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__)); // Ρίζα της εφαρμογής
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/drivetest'); // URL του project
}

// Ρυθμίσεις για τη βάση δεδομένων
$config = [
    'db_host' => 'localhost',
    'db_name' => 'drivetest',
    'db_user' => 'root',
    'db_pass' => '',

    // Διαδρομές αρχείων
    'app_root' => BASE_PATH,
    'public_path' => BASE_PATH . DIRECTORY_SEPARATOR . 'public',
    'assets_path' => BASE_PATH . DIRECTORY_SEPARATOR . 'assets',
    'includes_path' => BASE_PATH . DIRECTORY_SEPARATOR . 'includes',

    // Base URL για CSS, JS, εικόνες
    'base_url' => BASE_URL,

    // Ρυθμίσεις API
    'google_maps_api_key' => 'AIzaSyCgZpJWVYyrY0U8U1jBGelEWryur3vIrzc', // Ενεργοποίησε και επαλήθευσε το API Key στο Google Cloud Console
    'other_service_key' => 'OTHER_SERVICE_KEY_HERE', // Άλλα API Keys αν χρειάζονται

    // Ρυθμίσεις Γλωσσών
    'available_languages' => [
        'el' => 'Ελληνικά',
        'al' => 'Shqip',
        'ru' => 'Русский'
    ],
    'default_language' => 'el',

    // Ρυθμίσεις Τεστ
    'success_percentage_threshold' => 70, // Ελάχιστο ποσοστό επιτυχίας
    'questions_per_test' => 20, // Αριθμός ερωτήσεων ανά τεστ
    'test_types' => ['simulation', 'exercise', 'difficult', 'unanswered'],

    // Συνδέσεις με APIs
    'api_endpoints' => [
        'google_maps' => 'https://maps.googleapis.com/maps/api/',
        'other_service' => 'https://api.otherservice.com/'
    ],

    // Ρύθμιση για debugging (true για testing, false για production)
    'debug' => true // Ενεργοποίηση για testing, αλλάζεται σε false για παραγωγή
];

// 🔴 Διορθωμένος τρόπος ορισμού των Stripe API Keys
if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY', 'sk_test_51QoyZaIWtpG8xVdkMKh1yXUFtBu69ztv5bc6lJKUTkopVajQOsDnPnxJOEqTeXmKwgQFo6hcshubckzItICLbEOP00UgwPFfF3');
}
if (!defined('STRIPE_PUBLISHABLE_KEY')) {
    define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51QoyZaIWtpG8xVdkwhAG9w4A1zA2lEIHVfxWFgUGZM7TpptscXQ9L8MA4RNq7pcOcyuubx0YUJYhwKd7Le0AeXAM00VL6weqLe');
}

// Ορισμός σταθεράς DEBUG για συμβατότητα
if (!defined('DEBUG')) {
    define('DEBUG', $config['debug']);
}

// Επιστρέφουμε το config array
return $config;