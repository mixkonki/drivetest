<?php
require_once '../../config/config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: application/json');

// Ανάκτηση σχολών από τη βάση
$query = "SELECT name, latitude, longitude FROM schools WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$result = $mysqli->query($query);

$schools = [];
while ($row = $result->fetch_assoc()) {
    $schools[] = $row;
}

echo json_encode($schools);
?>
