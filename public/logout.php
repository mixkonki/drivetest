<?php
// Διαδρομή: /public/logout.php

session_start();
session_unset(); // Καθαρίζει όλες τις μεταβλητές συνεδρίας
session_destroy(); // Καταστρέφει τη συνεδρία

// Διαγραφή του cookie "remember_me" αν υπάρχει
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Ανακατεύθυνση στη σελίδα σύνδεσης μετά την αποσύνδεση
header("Location: login.php");
exit();
?>