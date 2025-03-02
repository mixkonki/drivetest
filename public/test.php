<?php
require_once '../includes/db_connection.php';

echo "<h2>✅ Η βάση δεδομένων συνδέθηκε επιτυχώς!</h2>";

$config = require_once '../config/config.php';
echo "<h3>🔹 Ρυθμίσεις:</h3><pre>";
print_r($config);
echo "</pre>";

$query = "SELECT * FROM users";
$result = $mysqli->query($query);

if ($result) {
    echo "<h3>🔹 Χρήστες στη βάση:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Όνομα: " . $row['fullname'] . " | Email: " . $row['email'] . "<br>";
    }
} else {
    echo "❌ Σφάλμα στη λήψη χρηστών: " . $mysqli->error;
}
?>
