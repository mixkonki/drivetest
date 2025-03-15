<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once BASE_PATH . '/includes/user_auth.php';
require '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'student') {
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$categories = $_POST['categories'] ?? [];
$durations = $_POST['durations'] ?? [];
$user_id = $_SESSION['user_id'];

if (empty($categories)) {
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=no_selection");
    exit();
}

$user_prices = [
    'Υποψηφίων Οδηγών' => 10, 'Χειριστών Μηχανημάτων Έργου' => 15,
    'ADR' => 20, 'ΠΕΕ' => 25, 'Ταχυπλόων' => 18, 'ΤΑΞΙ' => 12, 'Αυτοαξιολόγηση' => 8
];

$duration_multipliers = [1 => 1, 3 => 2.5, 6 => 4.5, 12 => 8];

$totalPrice = 0;
$subscriptionData = [];

foreach ($categories as $category) {
    $duration = $durations[$category] ?? 1;
    $price = $user_prices[$category] * $duration_multipliers[$duration];
    $totalPrice += $price;
    $subscriptionData[$category] = $duration;
}

$totalPriceCents = intval($totalPrice * 100);

// ✅ **ΧΡΗΣΗ `metadata` αντί για `client_reference_id`**
$metadata = [
    'user_id' => strval($user_id), // Πρέπει να είναι string
    'subscriptions' => json_encode($subscriptionData, JSON_UNESCAPED_UNICODE) // Μετατροπή σε JSON
];

// 🔍 DEBUG: Καταγραφή στο log
error_log("🔍 [checkout.php] Metadata: " . print_r($metadata, true));

try {
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => 'Συνδρομή DriveTest'],
                'unit_amount' => $totalPriceCents, 
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'metadata' => $metadata, // ✅ Εισαγωγή metadata αντί `client_reference_id`
        'success_url' => BASE_URL . "/subscriptions/success.php?session_id={CHECKOUT_SESSION_ID}",
        'cancel_url' => BASE_URL . "/subscriptions/buy.php"
    ]);

    error_log("✅ [checkout.php] Stripe Checkout Created: " . print_r($checkout_session, true));

    header("Location: " . $checkout_session->url);
    exit();
} catch (Exception $e) {
    error_log("🚨 Σφάλμα κατά τη δημιουργία Stripe Checkout: " . $e->getMessage());
    die("⚠️ Σφάλμα Stripe: " . htmlspecialchars($e->getMessage()));
}
