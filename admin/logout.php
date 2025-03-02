<?php
session_start();
session_unset(); // Καθαρίζει όλες τις μεταβλητές συνεδρίας
session_destroy(); // Καταστρέφει τη συνεδρία

// Ανακατεύθυνση στη σελίδα σύνδεσης μετά την αποσύνδεση
header("Location: ../public/login.php");
exit();
?>
