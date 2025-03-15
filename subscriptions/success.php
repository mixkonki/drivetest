<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../vendor/autoload.php';
require_once '../includes/mailer.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("🔄 success.php loaded");

// ✅ Έλεγχος αν υπάρχει `session_id`
$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    error_log("🚨 Missing session_id in URL. Redirecting...");
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=missing_data");
    exit();
}

try {
    // ✅ Ανάκτηση δεδομένων από το Stripe
    $session = \Stripe\Checkout\Session::retrieve($session_id);
    error_log("📌 Retrieved Stripe Session: " . print_r($session, true));

    // ✅ Εξαγωγή metadata - Χρήση array notation για ασφάλεια
    $metadata = $session->metadata->toArray() ?? [];
    error_log("📌 Metadata: " . print_r($metadata, true));

    $categories = json_decode($metadata['categories'] ?? "{}", true);
    $durations = json_decode($metadata['durations'] ?? "{}", true);
    $expiry_dates = json_decode($metadata['expiry_dates'] ?? "{}", true);

    // ✅ Debugging logs
    error_log("📌 Stripe Metadata Extracted:");
    error_log("📂 Categories: " . print_r($categories, true));
    error_log("⏳ Durations: " . print_r($durations, true));
    error_log("📆 Expiry Dates: " . print_r($expiry_dates, true));

    // ✅ Αν τα δεδομένα είναι κενά, σταματάμε
    if (empty($categories) || empty($durations) || empty($expiry_dates)) {
        error_log("🚨 Missing data in Stripe Metadata! Redirecting...");
        header("Location: " . BASE_URL . "/subscriptions/buy.php?error=missing_data");
        exit();
    }

    // ✅ Ανάκτησή email χρήστη από τη βάση
    $user_id = $_SESSION['user_id'];
    if (!$user_id) {
        error_log("🚨 User not logged in!");
        header("Location: " . BASE_URL . "/public/login.php?error=not_logged_in");
        exit();
    }

    $stmt = $mysqli->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("🚨 User email not found in database!");
        header("Location: " . BASE_URL . "/subscriptions/buy.php?error=user_not_found");
        exit();
    }

    $user_email = $user['email'];
    error_log("📧 User Email: " . $user_email);

    // ✅ Αποθήκευση ή ενημέρωση των συνδρομών στη βάση
    foreach ($categories as $category_id) {
        if (!isset($expiry_dates[$category_id]) || !isset($durations[$category_id])) {
            error_log("🚨 Missing expiry or duration for category $category_id");
            continue;
        }

        $expiry_date = $expiry_dates[$category_id];
        if (!isset($durations[$category_id]) || $durations[$category_id] === null) {
            error_log("🚨 Duration for category $category_id is missing or null, setting to default 1");
            $duration = 1; // Default διάρκεια 1 μήνα αν είναι null
        } else {
            $duration = intval($durations[$category_id]); // Μετατροπή σε integer για ασφάλεια
        }

        error_log("📝 Processing subscription for category ID: $category_id - New expiry: $expiry_date, Duration: $duration");

        $category_json = json_encode([$category_id]);
        $duration_json = json_encode([$category_id => $duration], JSON_FORCE_OBJECT);
        if ($duration_json === false || $duration_json === null) {
            error_log("🚨 Failed to encode duration JSON for category $category_id");
            $duration_json = json_encode([$category_id => 1], JSON_FORCE_OBJECT); // Default τιμή
        }
        error_log("📍 Duration JSON for category $category_id: " . $duration_json);

        // Έλεγχος για υπάρχουσα συνδρομή
        $check_query = "SELECT id FROM subscriptions WHERE user_id = ? AND JSON_CONTAINS(categories, ?)";
        $check_stmt = $mysqli->prepare($check_query);
        $check_stmt->bind_param("is", $user_id, $category_json);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            // ✅ Αν υπάρχει ήδη, ενημερώνουμε την ημερομηνία λήξης και το status
            error_log("🔄 Updating existing subscription for category: $category_id");
            $update_query = "UPDATE subscriptions SET expiry_date = ?, durations = ?, status = 'active' WHERE user_id = ? AND JSON_CONTAINS(categories, ?)";
            $update_stmt = $mysqli->prepare($update_query);
            $update_stmt->bind_param("ssis", $expiry_date, $duration_json, $user_id, $category_json);

            if (!$update_stmt->execute()) {
                error_log("🚨 Update Error for category $category_id: " . $mysqli->error);
                header("Location: " . BASE_URL . "/subscriptions/buy.php?error=db_failure");
                exit();
            }
        } else {
            // ✅ Αν δεν υπάρχει, προσθέτουμε νέα εγγραφή
            error_log("➕ Inserting new subscription for category: $category_id");
            $insert_query = "INSERT INTO subscriptions (user_id, categories, durations, expiry_date, status) VALUES (?, ?, ?, ?, 'active')";
            $insert_stmt = $mysqli->prepare($insert_query);
            $insert_stmt->bind_param("isss", $user_id, $category_json, $duration_json, $expiry_date);

            if (!$insert_stmt->execute()) {
                error_log("🚨 Insert Error for category $category_id: " . $mysqli->error);
                header("Location: " . BASE_URL . "/subscriptions/buy.php?error=db_failure");
                exit();
            }
        }

        $check_stmt->close();
        if (isset($update_stmt)) $update_stmt->close();
        if (isset($insert_stmt)) $insert_stmt->close();
    }

    // ✅ Ενημέρωση του subscription_status στον πίνακα users σε 'active' αν υπάρχουν ενεργές συνδρομές
    $check_active_query = "SELECT COUNT(*) FROM subscriptions WHERE user_id = ? AND status = 'active' AND expiry_date > NOW()";
    $check_stmt = $mysqli->prepare($check_active_query);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_array()[0];
    $check_stmt->close();

    if ($check_result > 0) {
        $update_user_query = "UPDATE users SET subscription_status = 'active' WHERE id = ?";
        $update_stmt = $mysqli->prepare($update_user_query);
        $update_stmt->bind_param("i", $user_id);

        if (!$update_stmt->execute()) {
            error_log("🚨 Failed to update subscription_status in users table: " . $mysqli->error);
        }
        $update_stmt->close();
    }

    // ✅ Δημιουργία email επιβεβαίωσης
    $subject = "🚗 Επιβεβαίωση Συνδρομής - DriveTest";
    
    // ✅ Ανάκτηση ονομάτων κατηγοριών από τη βάση
    $category_names = [];
    $query = "SELECT id, name FROM subscription_categories WHERE id IN (" . implode(',', array_map('intval', $categories)) . ")";
    $result = $mysqli->query($query);
    while ($row = $result->fetch_assoc()) {
        $category_names[$row['id']] = $row['name'];
    }

    $subscription_list = "";
    foreach ($categories as $category_id) {
        $expiry = date("d M Y", strtotime($expiry_dates[$category_id]));
        $category_name = $category_names[$category_id] ?? "Άγνωστη Κατηγορία";
        $subscription_list .= "<li>🔹 <strong>{$category_name}</strong> - <strong>Λήξη:</strong> {$expiry}</li>";
    }

    $message = "
    <html>
    <head>
        <title>Επβεβαίωση Συνδρομής</title>
    </head>
    <body style='font-family: Arial, sans-serif; color: #333;'>
        <h2>🚗 Αγαπητέ/ή χρήστη,</h2>
        <p>Σας ενημερώνουμε ότι η αγορά/ανανέωση της συνδρομής σας ολοκληρώθηκε επιτυχώς.</p>
        <p>Οι λεπτομέρειες της συνδρομής σας είναι οι εξής:</p>
        <ul style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; list-style: none;'>
            {$subscription_list}
        </ul>
        <p>Μπορείτε να ξεκινήσετε την προετοιμασία σας άμεσα!</p>
        <p>🎯 <a href='" . BASE_URL . "/tests/index.php' style='color: #007bff; font-weight: bold;'>Κάντε τα τεστ σας τώρα</a></p>
        <p>Σας ευχαριστούμε που επιλέξατε το <strong>DriveTest</strong>. 🚀</p>
        <br>
        <p>Με εκτίμηση,<br>Η ομάδα του <strong>DriveTest</strong></p>
    </body>
    </html>";

    // ✅ Αποστολή email μέσω PHPMailer
    if (send_mail($user_email, $subject, $message)) {
        error_log("📧 Email επιβεβαίωσης εστάλη!");
    } else {
        error_log("❌ Σφάλμα αποστολής email!");
    }

    // ✅ Ανακατεύθυνση στον πίνακα ελέγχου
    error_log("🔀 Redirecting to dashboard...");
    header("Location: " . BASE_URL . "/users/dashboard.php?payment=success");
    exit();

} catch (\Exception $e) {
    error_log("🚨 Stripe API Error: " . $e->getMessage());
    header("Location: " . BASE_URL . "/subscriptions/buy.php?error=stripe_failure");
    exit();
}