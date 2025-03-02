<?php
$plain_password = "123456"; // Προσθέτουμε escape στον ειδικό χαρακτήρα $
$new_hash = password_hash($plain_password, PASSWORD_DEFAULT);

echo "Νέο Hash: " . $new_hash;
?>
