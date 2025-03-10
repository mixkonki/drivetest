/**
 * DriveTest Question Manager JS
 * Διαχείριση ερωτήσεων και μαζικές λειτουργίες
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Question Manager JS loaded');
    
    // Αρχικοποίηση μεταβλητών
    let questionsData = [];
    let selectedQuestions = new Set(); // Set για επιλεγμένες ερωτήσεις
    const questionsTableBody = document.getElementById('questions-table-body');
    const questionsContainer = document.getElementById('question-list-container');
    const formContainer = document.getElementById('question-form-container');
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const addQuestionBtn = document.getElementById('add-question-btn');
    const backToListBtn = document.getElementById('back-to-list-btn');
    
    // Διαδρομή για τις ενέργειες API
   // Διαδρομή για τις ενέργειες API
// Διαδρομή για τις ενέργειες API
const apiUrl = getBaseUrl() + '/drivetest/admin/test/question_actions.php';
    
    // Φόρτωση ερωτήσεων κατά την αρχικοποίηση
    loadQuestions();
    
    // ======== Event Listeners ========
    
    // Επιστροφή στη λίστα από τη φόρμα
    if (backToListBtn) {
        backToListBtn.addEventListener('click', function() {
            showQuestionsList();
        });
    }
    
    // Προσθήκη νέας ερώτησης
    if (addQuestionBtn) {
        addQuestionBtn.addEventListener('click', function() {
            showQuestionForm();
        });
    }
    
    // Έλεγχος για master checkbox
    const masterCheckbox = document.getElementById('select-all-questions');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            selectAllQuestions(this.checked);
        });
    }
    
    // Χειρισμός URL παραμέτρων κατά τη φόρτωση
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'add') {
        showQuestionForm();
    }
    
    // ======== Functions ========
    
    // Φόρτωση ερωτήσεων από το API
    function loadQuestions() {
        if (!questionsTableBody) return;
        
        questionsTableBody.innerHTML = '<tr><td colspan="10">Φόρτωση ερωτήσεων...</td></tr>';
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list_questions'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                questionsData = data.questions;
                renderQuestions();
            } else {
                questionsTableBody.innerHTML = `<tr><td colspan="10">Σφάλμα: ${data.message}</td></tr>`;
                console.error('Error loading questions:', data.message);
            }
        })
        .catch(error => {
            questionsTableBody.innerHTML = `<tr><td colspan="10">Σφάλμα επικοινωνίας με τον server</td></tr>`;
            console.error('Error fetching questions:', error);
        });
    }
    
    // Απεικόνιση των ερωτήσεων στον πίνακα
    function renderQuestions() {
        if (!questionsTableBody || !questionsData || !questionsData.length) {
            questionsTableBody.innerHTML = '<tr><td colspan="10">Δεν βρέθηκαν ερωτήσεις</td></tr>';
            return;
        }
        
        questionsTableBody.innerHTML = '';
        
        questionsData.forEach(question => {
            const row = document.createElement('tr');
            const isSelected = selectedQuestions.has(parseInt(question.id));
            
            if (isSelected) {
                row.classList.add('selected-row');
            }
            
            // Δημιουργία του checkbox επιλογής για μαζικές ενέργειες
            const checkboxCell = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'question-checkbox';
            checkbox.dataset.id = question.id;
            checkbox.checked = isSelected;
            checkbox.addEventListener('change', function() {
                toggleQuestionSelection(question.id, this.checked);
            });
            checkboxCell.appendChild(checkbox);
            row.appendChild(checkboxCell);
            
            // Περικοπή μεγάλων κειμένων και αποφυγή HTML ετικετών
            const questionText = question.question_text.length > 100 ? 
                                question.question_text.substring(0, 97) + '...' : 
                                question.question_text;
            
            row.innerHTML += `
                <td>${htmlEscape(questionText)}</td>
                <td>${htmlEscape(question.category_name)}</td>
                <td>${parseInt(question.answers_count) || 0}</td>
                <td>${getQuestionTypeLabel(question.question_type)}</td>
                <td>${formatDate(question.created_at)}</td>
                <td>${question.status === 'active' ? '<span class="badge-active">Ενεργή</span>' : '<span class="badge-inactive">Ανενεργή</span>'}</td>
                <td>${question.author || 'Άγνωστος'}</td>
                <td>${question.id}</td>
                <td>
                    <button type="button" class="btn-icon btn-edit" data-id="${question.id}" title="Επεξεργασία">✏️</button>
                    <button type="button" class="btn-icon btn-delete" data-id="${question.id}" title="Διαγραφή">🗑️</button>
                </td>
            `;
            
            questionsTableBody.appendChild(row);
        });
        
        // Προσθήκη event listeners για τα κουμπιά
        addQuestionButtonListeners();
        
        // Ενημέρωση του counter μαζικών επιλογών
        updateBulkSelectionCounter();
    }
    
    // Προσθήκη listeners για τα κουμπιά επεξεργασίας και διαγραφής
    function addQuestionButtonListeners() {
        // Edit buttons
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function() {
                const questionId = this.getAttribute('data-id');
                editQuestion(questionId);
            });
        });
        
        // Delete buttons
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function() {
                const questionId = this.getAttribute('data-id');
                deleteQuestion(questionId);
            });
        });
    }
    
    // Συνάρτηση για εναλλαγή επιλογής ερώτησης
    function toggleQuestionSelection(questionId, isSelected) {
        questionId = parseInt(questionId);
        
        if (isSelected) {
            selectedQuestions.add(questionId);
        } else {
            selectedQuestions.delete(questionId);
        }
        
        // Ενημέρωση του UI
        updateQuestionRowStyle(questionId, isSelected);
        updateBulkSelectionCounter();
    }
    
    // Ενημέρωση του στυλ γραμμής βάσει επιλογής
    function updateQuestionRowStyle(questionId, isSelected) {
        const checkbox = document.querySelector(`.question-checkbox[data-id="${questionId}"]`);
        if (checkbox) {
            const row = checkbox.closest('tr');
            if (isSelected) {
                row.classList.add('selected-row');
            } else {
                row.classList.remove('selected-row');
            }
        }
    }
    
    // Επιλογή/αποεπιλογή όλων των ερωτήσεων
    function selectAllQuestions(selectAll) {
        document.querySelectorAll('.question-checkbox').forEach(checkbox => {
            const questionId = parseInt(checkbox.dataset.id);
            checkbox.checked = selectAll;
            toggleQuestionSelection(questionId, selectAll);
        });
    }
    
    // Ενημέρωση του μετρητή επιλεγμένων ερωτήσεων
    function updateBulkSelectionCounter() {
        const counter = document.getElementById('selected-count');
        const bulkActionsPanel = document.getElementById('bulk-actions-bar');
        
        if (counter) {
            counter.textContent = selectedQuestions.size;
        }
        
        if (bulkActionsPanel) {
            if (selectedQuestions.size > 0) {
                bulkActionsPanel.style.display = 'flex';
            } else {
                bulkActionsPanel.style.display = 'none';
            }
        }
    }
    
    // Μαζική διαγραφή των επιλεγμένων ερωτήσεων
    function bulkDeleteQuestions() {
        if (selectedQuestions.size === 0) {
            alert('Δεν έχετε επιλέξει ερωτήσεις για διαγραφή.');
            return;
        }
        
        if (!confirm(`Είστε σίγουροι ότι θέλετε να διαγράψετε ${selectedQuestions.size} ερωτήσεις; Η ενέργεια είναι μη αναστρέψιμη!`)) {
            return;
        }
        
        const questionIds = Array.from(selectedQuestions);
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=bulk_delete&question_ids=${JSON.stringify(questionIds)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Επιτυχής διαγραφή
                showNotification('success', data.message);
                
                // Αφαίρεση των διαγραμμένων ερωτήσεων από τον πίνακα
                if (data.deleted_count > 0) {
                    // Επαναφόρτωση των ερωτήσεων
                    loadQuestions();
                }
                
                // Καθαρισμός επιλογών
                selectedQuestions.clear();
                updateBulkSelectionCounter();
                
                // Εμφάνιση προειδοποίησης για ερωτήσεις που παραλείφθηκαν
                if (data.skipped_ids && data.skipped_ids.length > 0) {
                    const skippedMsg = `${data.skipped_ids.length} ερωτήσεις παραλείφθηκαν επειδή χρησιμοποιούνται σε τεστ.`;
                    showNotification('warning', skippedMsg);
                }
            } else {
                // Αποτυχία διαγραφής
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error during bulk delete:', error);
            showNotification('error', 'Σφάλμα επικοινωνίας με τον server');
        });
    }
    
    // Διαγραφή μεμονωμένης ερώτησης
    function deleteQuestion(questionId) {
        if (!confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτή την ερώτηση;')) {
            return;
        }
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_question&id=${questionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                
                // Αφαίρεση της ερώτησης από τον πίνακα
                questionsData = questionsData.filter(q => q.id != questionId);
                renderQuestions();
                
                // Αφαίρεση από τις επιλεγμένες ερωτήσεις αν υπήρχε
                if (selectedQuestions.has(parseInt(questionId))) {
                    selectedQuestions.delete(parseInt(questionId));
                    updateBulkSelectionCounter();
                }
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error deleting question:', error);
            showNotification('error', 'Σφάλμα επικοινωνίας με τον server');
        });
    }
    
    // Εμφάνιση notification popup
    function showNotification(type, message) {
        // Έλεγχος αν υπάρχει ήδη notification container
        let notificationContainer = document.getElementById('notification-container');
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.id = 'notification-container';
            document.body.appendChild(notificationContainer);
        }
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close">×</button>
            </div>
        `;
        
        notificationContainer.appendChild(notification);
        
        // Αυτόματο κλείσιμο μετά από 5 δευτερόλεπτα
        setTimeout(() => {
            notification.classList.add('notification-hide');
            setTimeout(() => {
                notificationContainer.removeChild(notification);
            }, 300);
        }, 5000);
        
        // Κλείσιμο με το κουμπί X
        notification.querySelector('.notification-close').addEventListener('click', function() {
            notification.classList.add('notification-hide');
            setTimeout(() => {
                notificationContainer.removeChild(notification);
            }, 300);
        });
    }
    
    // Επεξεργασία ερώτησης (θα ανακτήσει τα δεδομένα και θα εμφανίσει τη φόρμα)
    function editQuestion(questionId) {
        // Ανακατεύθυνση στην υπάρχουσα σελίδα edit_question.php
        window.location.href = `${getBaseUrl()}/admin/test/edit_question.php?id=${questionId}`;
    }
    
    // Αλλαγή ορατότητας μεταξύ λίστας και φόρμας
    function showQuestionsList() {
        if (questionsContainer) questionsContainer.style.display = 'block';
        if (formContainer) formContainer.style.display = 'none';
    }
    
    function showQuestionForm() {
        if (questionsContainer) questionsContainer.style.display = 'none';
        if (formContainer) formContainer.style.display = 'block';
    }
    
    // Βοηθητικές συναρτήσεις
    
    // Μορφοποίηση ημερομηνίας
    function formatDate(dateString) {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('el-GR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }
    
    // Μετατροπή τύπου ερώτησης σε αναγνώσιμη μορφή
    function getQuestionTypeLabel(type) {
        const types = {
            'single_choice': 'Μονής Επιλογής',
            'multiple_choice': 'Πολλαπλής Επιλογής',
            'true_false': 'Σωστό/Λάθος',
            'fill_in_blank': 'Συμπλήρωση Κενού',
            'matching': 'Αντιστοίχιση',
            'ordering': 'Ταξινόμηση',
            'short_answer': 'Σύντομη Απάντηση',
            'essay': 'Ανάπτυξη'
        };
        return types[type] || type;
    }
    
    // Αποφυγή XSS με escape των HTML χαρακτήρων
    function htmlEscape(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    
    // Βοηθητική συνάρτηση για την εύρεση του BASE_URL
    function getBaseUrl() {
        const baseElement = document.querySelector('base');
        if (baseElement) return baseElement.href;
        
        // Fallback - εξαγωγή από link ή script tags
        const scriptTags = document.querySelectorAll('script[src]');
        for (let i = 0; i < scriptTags.length; i++) {
            const src = scriptTags[i].getAttribute('src');
            if (src.includes('/admin/assets/js/')) {
                return src.split('/admin/assets/js/')[0];
            }
        }
        
        return '';
    }
    
    // Εξαγωγή των απαραίτητων συναρτήσεων για χρήση εκτός του event listener
    window.bulkDeleteQuestions = bulkDeleteQuestions;
    window.selectAllQuestions = selectAllQuestions;
});