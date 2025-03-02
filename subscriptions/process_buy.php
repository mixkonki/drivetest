<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    error_log("❌ ERROR: Ο χρήστης δεν είναι συνδεδεμένος.");
    header("Location: " . BASE_URL . "/public/login.php");
    exit();
}

error_log("🛠️ POST Data Received: " . print_r($_POST, true));

$categories = $_POST['categories'] ?? [];
$durations = $_POST['durations'] ?? [];

if (empty($categories) || empty($durations)) {
    error_log("🚨 Missing Data: Categories or durations are empty.");
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=missing_data");
    exit();
}

// ✅ Καθαρισμός δεδομένων
$formatted_categories = [];
foreach ($categories as $category_id) {
    if (!empty($durations[$category_id])) {
        $formatted_categories[$category_id] = intval($durations[$category_id]);
    }
}

error_log("🔍 Formatted Categories & Durations: " . print_r($formatted_categories, true));

if (empty($formatted_categories)) {
    error_log("🚨 No valid category-duration pairs.");
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=invalid_data");
    exit();
}

// ✅ Ανάκτηση ενεργών συνδρομών
$query = "SELECT categories, expiry_date FROM subscriptions WHERE user_id = ? AND expiry_date > NOW()";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$existing_subscriptions = [];
while ($row = $result->fetch_assoc()) {
    $user_categories = json_decode($row['categories'], true);
    foreach ($user_categories as $cat) {
        $existing_subscriptions[$cat] = $row['expiry_date'];
    }
}
$stmt->close();

error_log("📌 Existing Subscriptions: " . print_r($existing_subscriptions, true));

// ✅ Υπολογισμός νέων ημερομηνιών λήξης
$totalPrice = 0;
$new_expiry_dates = [];
$price_per_month = 10;

foreach ($formatted_categories as $category_id => $months) {
    if (isset($existing_subscriptions[$category_id])) {
        $expiry_date = date('Y-m-d', strtotime($existing_subscriptions[$category_id] . " + $months months"));
    } else {
        $expiry_date = date('Y-m-d', strtotime("+$months months"));
    }

    $new_expiry_dates[$category_id] = $expiry_date;
    $totalPrice += $price_per_month * $months;
}

error_log("📆 New Expiry Dates: " . print_r($new_expiry_dates, true));
error_log("💰 Total Price: €" . $totalPrice);

if ($totalPrice <= 0) {
    error_log("🚨 ERROR: Invalid total price calculated.");
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=invalid_price");
    exit();
}

// ✅ Δημιουργία πληρωμής στο Stripe
try {
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => 'Συνδρομή DriveTest'],
                'unit_amount' => intval($totalPrice * 100),
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'metadata' => [
            'user_id' => strval($user_id),
            'categories' => json_encode(array_keys($formatted_categories), JSON_UNESCAPED_UNICODE),
            'durations' => json_encode($formatted_categories, JSON_UNESCAPED_UNICODE),
            'expiry_dates' => json_encode($new_expiry_dates, JSON_UNESCAPED_UNICODE)
        ],
        'success_url' => BASE_URL . "/subscriptions/success.php?session_id={CHECKOUT_SESSION_ID}",
        'cancel_url' => BASE_URL . "/subscriptions/buy.php"
    ]);

    error_log("✅ Payment session created: " . $checkout_session->id);
    header("Location: " . $checkout_session->url);
    exit();
} catch (Exception $e) {
    error_log("🚨 Stripe Checkout Error: " . $e->getMessage());
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=payment_failed");
    exit();
}
?>
