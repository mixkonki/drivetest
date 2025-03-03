<?php
// Αρχείο: includes/flash_messages.php - Εμφανίζει τα flash messages
$messages = getFlashMessages();

foreach ($messages as $type => $message):
    $alertClass = 'alert-info';
    
    // Καθορισμός της κλάσης ειδοποίησης με βάση τον τύπο του μηνύματος
    switch ($type) {
        case 'success':
            $alertClass = 'alert-success';
            break;
        case 'error':
            $alertClass = 'alert-danger';
            break;
        case 'warning':
            $alertClass = 'alert-warning';
            break;
        case 'info':
            $alertClass = 'alert-info';
            break;
    }
?>
<div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endforeach; ?>