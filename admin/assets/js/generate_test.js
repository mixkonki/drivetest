document.addEventListener('DOMContentLoaded', function() {
    console.log('Generate Test JS loaded');
    
    const configSelect = document.getElementById('config_id');
    const configDetails = document.getElementById('config-details');
    const configQuestions = document.getElementById('config-questions');
    const configTime = document.getElementById('config-time');
    const configPass = document.getElementById('config-pass');
    const configMethod = document.getElementById('config-method');
    
    // Εμφάνιση λεπτομερειών της επιλεγμένης ρύθμισης
    configSelect.addEventListener('change', function() {
        if (this.value) {
            const option = this.options[this.selectedIndex];
            const questions = option.getAttribute('data-questions');
            const time = option.getAttribute('data-time');
            const pass = option.getAttribute('data-pass');
            const method = option.getAttribute('data-method');
            
            configQuestions.textContent = questions;
            configTime.textContent = time;
            configPass.textContent = pass;
            
            // Μεταφράζουμε τη μέθοδο επιλογής
            let methodText = '';
            switch(method) {
                case 'random':
                    methodText = 'Τυχαία';
                    break;
                case 'proportional':
                    methodText = 'Αναλογική';
                    break;
                case 'fixed':
                    methodText = 'Σταθερός αριθμός ανά κεφάλαιο';
                    break;
                default:
                    methodText = method;
            }
            configMethod.textContent = methodText;
            
            configDetails.style.display = 'block';
        } else {
            configDetails.style.display = 'none';
        }
    });
    
    // Διαχείριση διαγραφής τεστ
    document.querySelectorAll('.delete-test').forEach(button => {
        button.addEventListener('click', function() {
            const testId = this.getAttribute('data-id');
            
            if (confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το τεστ;')) {
                // Αποστολή αιτήματος διαγραφής
                fetch(`${getBaseUrl()}/admin/test/delete_test.php?id=${testId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Επιτυχής διαγραφή, ανανέωση της σελίδας
                        window.location.reload();
                    } else {
                        // Εμφάνιση μηνύματος σφάλματος
                        alert(`Σφάλμα: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error deleting test:', error);
                    alert(`Σφάλμα επικοινωνίας με τον server: ${error.message}`);
                });
            }
        });
    });
    
    // Βοηθητική συνάρτηση για την εύρεση του BASE_URL
    function getBaseUrl() {
        const baseElement = document.querySelector('base');
        if (baseElement) return baseElement.href;
        
        // Fallback - extract from link or script tags
        const scriptTags = document.querySelectorAll('script[src]');
        for (let i = 0; i < scriptTags.length; i++) {
            const src = scriptTags[i].getAttribute('src');
            if (src.includes('/admin/assets/js/')) {
                return src.split('/admin/assets/js/')[0];
            }
        }
        
        return '';
    }
});