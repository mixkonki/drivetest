document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('user-filter-form');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch('/drivetest/admin/api/users.php?action=filter', { // Στατική διαδρομή (συμβατή με το WAMP)
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('users-table-body').innerHTML = data.html;
            } else {
                console.error('Σφάλμα:', data.message);
            }
        })
        .catch(error => console.error('AJAX Error:', error));
    });
});