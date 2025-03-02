<?php
session_start();
require_once '../config/config.php';
require_once '../config/google_config.php';
require_once '../includes/db_connection.php';
require_once '../vendor/autoload.php'; // Φόρτωση του Google API Client

use Google\Client;
use Google\Service\Oauth2;

$google_client = new Client();
$google_client->setClientId(GOOGLE_CLIENT_ID);
$google_client->setClientSecret(GOOGLE_CLIENT_SECRET);
$google_client->setRedirectUri(GOOGLE_REDIRECT_URL);
$google_client->addScope(GOOGLE_SCOPES);

if (isset($_GET['code'])) {
    $token = $google_client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        $google_client->setAccessToken($token['access_token']);
        $_SESSION['google_access_token'] = $token['access_token'];

        $google_service = new Oauth2($google_client);
        $user_data = $google_service->userinfo->get();

        $email = $user_data->email;
        $fullname = $user_data->name;
        $google_id = $user_data->id;

        // Έλεγχος αν ο χρήστης υπάρχει ήδη στη βάση
        $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            // Αν ο χρήστης υπάρχει, απλά ενημερώνουμε το session
            $_SESSION['user_email'] = $email;
            $_SESSION['user_fullname'] = $fullname;
        } else {
            // Αν ο χρήστης δεν υπάρχει, τον εγγράφουμε
            $verification_token = bin2hex(random_bytes(32));
            $stmt = $mysqli->prepare("INSERT INTO users (fullname, email, role, verification_token, email_verified) VALUES (?, ?, 'user', ?, 1)");
            $stmt->bind_param("sss", $fullname, $email, $verification_token);
            if ($stmt->execute()) {
                $_SESSION['user_email'] = $email;
                $_SESSION['user_fullname'] = $fullname;
                // Στέλνουμε email επιβεβαίωσης (προαιρετικό, αφού το email_verified είναι 1)
                send_verification_email($email, $verification_token, $language);
            }
            $stmt->close();
        }
        $check_stmt->close();

        // Ανακατεύθυνση στην αρχική σελίδα ή στη σελίδα εγγραφής
        header('Location: ' . BASE_URL . '/public/index.php?lang=' . $language);
        exit;
    } else {
        // Σφάλμα κατά την ανάκτηση token
        header('Location: ' . BASE_URL . '/public/register_user.php?lang=' . $language . '&error=google_auth_failed');
        exit;
    }
} else {
    // Αν δεν υπάρχει κωδικός, ανακατεύθυνση πίσω
    header('Location: ' . BASE_URL . '/public/register_user.php?lang=' . $language);
    exit;
}