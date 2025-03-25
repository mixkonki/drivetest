document.addEventListener('DOMContentLoaded', function() {
    console.log('Generate Test JS loaded');
    
    const configSelect = document.getElementById('config_id');
    const configDetails = document.getElementById('config-details');
    const configQuestions = document.getElementById('config-questions');
    const configTime = document.getElementById('config-time');
    const configPass = document.getElementById('config-pass');
    const configMethod = document.getElementById('config-method');
    const configType = document.getElementById('config-type');
    const configAnswersMode = document.getElementById('config-answers-mode');
    const configExplanations = document.getElementById('config-explanations');
    const configRandomizeQ = document.getElementById('config-randomize-q');
    const configRandomizeA = document.getElementById('config-randomize-a');
    const primaryColorBox = document.getElementById('primary-color-box');
    const bgColorBox = document.getElementById('bg-color-box');
    
    // Προγραμματισμός τεστ
    const isScheduledCheckbox = document.getElementById('is_scheduled');
    const scheduleDateContainer = document.getElementById('schedule_date_container');
    
    if (isScheduledCheckbox) {
        isScheduledCheckbox.addEventListener('change', function() {
            scheduleDateContainer.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Εμφάνιση λεπτομερειών της επιλεγμένης ρύθμισης
    if (configSelect) {
        configSelect.addEventListener('change', function() {
            if (this.value) {
                const option = this.options[this.selectedIndex];
                const questions = option.getAttribute('data-questions');
                const time = option.getAttribute('data-time');
                const pass = option.getAttribute('data-pass');
                const method = option.getAttribute('data-method');
                const isPractice = option.getAttribute('data-practice') === '1';
                const isSimulation = option.getAttribute('data-simulation') === '1';
                const randomizeQ = option.getAttribute('data-randomize-q') === '1';
                const randomizeA = option.getAttribute('data-randomize-a') === '1';
                const answersMode = option.getAttribute('data-answers-mode');
                const showExplanations = option.getAttribute('data-show-explanations') === '1';
                const primaryColor = option.getAttribute('data-color');
                const bgColor = option.getAttribute('data-bg-color');
                
                configQuestions.textContent = questions;
                configTime.textContent = time === '0' ? 'Απεριόριστος' : time;
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
                
                // Εμφάνιση τύπου τεστ
                let typeText = [];
                if (isPractice) typeText.push('Εξάσκηση');
                if (isSimulation) typeText.push('Προσομοίωση');
                if (typeText.length === 0) typeText.push('Κανονικό');
                configType.textContent = typeText.join(', ');
                
                // Εμφάνιση τρόπου εμφάνισης απαντήσεων
                let answersModeText = '';
                switch(answersMode) {
                    case 'end_of_test':
                        answersModeText = 'Στο τέλος του τεστ';
                        break;
                    case 'after_each_question':
                        answersModeText = 'Μετά από κάθε ερώτηση';
                        break;
                    case 'never':
                        answersModeText = 'Ποτέ';
                        break;
                    default:
                        answersModeText = answersMode;
                }
                configAnswersMode.textContent = answersModeText;
                
                // Εμφάνιση επεξηγήσεων
                configExplanations.textContent = showExplanations ? 'Ναι' : 'Όχι';
                
                // Εμφάνιση τυχαίας σειράς
                configRandomizeQ.textContent = randomizeQ ? 'Ναι' : 'Όχι';
                configRandomizeA.textContent = randomizeA ? 'Ναι' : 'Όχι';
                
                // Εμφάνιση χρωμάτων
                if (primaryColorBox && bgColorBox) {
                    primaryColorBox.style.backgroundColor = primaryColor || '#aa3636';
                    bgColorBox.style.backgroundColor = bgColor || '#f5f5f5';
                }
                
                configDetails.style.display = 'block';
            } else {
                configDetails.style.display = 'none';
            }
        });
        configSelect.addEventListener('change', function() {
            if (this.value) {
                // Αυτό το τμήμα κώδικα έχει ήδη συμπληρωθεί από πριν
                
                configDetails.style.display = 'block';
            } else {
                configDetails.style.display = 'none';
            }
        });
    }
    
    // Φιλτράρισμα τεστ στον πίνακα
    const testSearch = document.getElementById('test-search');
    const statusFilter = document.getElementById('status-filter');
    const typeFilter = document.getElementById('type-filter');
    const clearFiltersBtn = document.getElementById('clear-filters');
    
    if (testSearch && statusFilter && typeFilter && clearFiltersBtn) {
        const filterTests = function() {
            const searchText = testSearch.value.toLowerCase();
            const statusValue = statusFilter.value;
            const typeValue = typeFilter.value;
            
            document.querySelectorAll('.admin-table tbody tr').forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const label = row.cells[1].textContent.toLowerCase();
                const category = row.cells[2].textContent.toLowerCase();
                const type = row.getAttribute('data-type');
                const status = row.getAttribute('data-status');
                
                const matchesSearch = !searchText || 
                    name.includes(searchText) || 
                    label.includes(searchText) || 
                    category.includes(searchText);
                
                const matchesStatus = !statusValue || status === statusValue;
                const matchesType = !typeValue || type === typeValue;
                
                if (matchesSearch && matchesStatus && matchesType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        };
        
        testSearch.addEventListener('input', filterTests);
        statusFilter.addEventListener('change', filterTests);
        typeFilter.addEventListener('change', filterTests);
        
        clearFiltersBtn.addEventListener('click', function() {
            testSearch.value = '';
            statusFilter.value = '';
            typeFilter.value = '';
            filterTests();
        });
    }
    
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
    
    // Διαχείριση ενεργοποίησης/απενεργοποίησης τεστ
    document.querySelectorAll('.toggle-test').forEach(button => {
        button.addEventListener('click', function() {
            const testId = this.getAttribute('data-id');
            const newStatus = this.getAttribute('data-status');
            const statusText = newStatus === 'active' ? 'ενεργοποιήσετε' : 'απενεργοποιήσετε';
            
            if (confirm(`Είστε σίγουροι ότι θέλετε να ${statusText} αυτό το τεστ;`)) {
                // Αποστολή αιτήματος αλλαγής κατάστασης
                fetch(`${getBaseUrl()}/admin/test/toggle_test_status.php?id=${testId}&status=${newStatus}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Επιτυχής αλλαγή, ανανέωση της σελίδας
                        window.location.reload();
                    } else {
                        // Εμφάνιση μηνύματος σφάλματος
                        alert(`Σφάλμα: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error toggling test status:', error);
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