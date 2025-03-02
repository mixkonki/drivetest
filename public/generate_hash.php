<?php
$password = "testpassword123"; // Βάλε τον κωδικό που θέλεις
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo "Κρυπτογραφημένος Κωδικός: " . $hashed_password;
?>
