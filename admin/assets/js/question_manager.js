/**
 * DriveTest Question Manager JS
 * Διαχείριση ερωτήσεων και μαζικές λειτουργίες
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Question Manager JS loaded');
    
    // Αρχικοποίηση μεταβλητών
    let questionsData = [];
    let filteredQuestions = []; // Για αποθήκευση των φιλτραρισμένων ερωτήσεων
    let selectedQuestions = new Set(); // Set για επιλεγμένες ερωτήσεις
    let subcategories = []; // Για αποθήκευση των υποκατηγοριών
    let chapters = []; // Για αποθήκευση των κεφαλαίων
    
    const questionsTableBody = document.getElementById('questions-table-body');
    const questionsContainer = document.getElementById('question-list-container');
    const formContainer = document.getElementById('question-form-container');
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const addQuestionBtn = document.getElementById('add-question-btn');
    const backToListBtn = document.getElementById('back-to-list-btn');
    
    // Στοιχεία για τα φίλτρα
    const toggleFiltersBtn = document.getElementById('toggle-filters-btn');
    const filtersPanel = document.getElementById('filters-panel');
    const filterCategory = document.getElementById('filter-category');
    const filterSubcategory = document.getElementById('filter-subcategory');
    const filterChapter = document.getElementById('filter-chapter');
    const filterType = document.getElementById('filter-type');
    const filterStatus = document.getElementById('filter-status');
    const filterSearch = document.getElementById('filter-search');
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    const resetFiltersBtn = document.getElementById('reset-filters-btn');
    const filteredCountElem = document.getElementById('filtered-count');
    const totalCountElem = document.getElementById('total-count');
    
    // ⚠️ ΔΙΟΡΘΩΣΗ: Απευθείας διαδρομή προς το API (χωρίς χρήση της getBaseUrl)
    const apiUrl = 'http://localhost/drivetest/admin/test/question_actions.php';
    console.log('Χρησιμοποιείται API URL:', apiUrl);
    
    // Φόρτωση ερωτήσεων και κατηγοριών κατά την αρχικοποίηση
    loadQuestions();
    loadSubcategories();
    
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
    
    // Toggle για το panel φίλτρων
    if (toggleFiltersBtn) {
        toggleFiltersBtn.addEventListener('click', function() {
            const isVisible = filtersPanel.style.display !== 'none';
            filtersPanel.style.display = isVisible ? 'none' : 'block';
            
            // Αλλαγή του εικονιδίου
            const expandIcon = this.querySelector('.expand-icon');
            const collapseIcon = this.querySelector('.collapse-icon');
            
            if (isVisible) {
                expandIcon.style.display = 'inline';
                collapseIcon.style.display = 'none';
            } else {
                expandIcon.style.display = 'none';
                collapseIcon.style.display = 'inline';
            }
        });
    }
    
    // Όταν αλλάζει η κατηγορία, να φορτώνονται οι αντίστοιχες υποκατηγορίες
    if (filterCategory) {
        filterCategory.addEventListener('change', function() {
            populateSubcategories(this.value);
        });
    }
    
    // Όταν αλλάζει η υποκατηγορία, να φορτώνονται τα αντίστοιχα κεφάλαια
    if (filterSubcategory) {
        filterSubcategory.addEventListener('change', function() {
            populateChapters(this.value);
        });
    }
    
    // Listener για αναζήτηση (keyup)
    if (filterSearch) {
        filterSearch.addEventListener('keyup', function(e) {
            // Αν πατηθεί Enter, εφαρμόζεται το φίλτρο
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
    
    // Εφαρμογή φίλτρων
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', function() {
            applyFilters();
        });
    }
    
    // Επαναφορά φίλτρων
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', function() {
            resetFilters();
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
    
    // ======== Functions για τα Φίλτρα ========
    
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
                
                // Επεξεργασία των δεδομένων για να προσθέσουμε τυχόν ελλείποντα πεδία
                questionsData.forEach(question => {
                    // Βεβαιωνόμαστε ότι υπάρχουν όλα τα απαραίτητα πεδία
                    if (!question.subcategory_id && question.subcategory_name) {
                        // Αναζήτηση του ID υποκατηγορίας από το όνομα
                        const subcategory = subcategories.find(s => s.name === question.subcategory_name);
                        if (subcategory) {
                            question.subcategory_id = subcategory.id;
                        }
                    }
                    
                    // Βεβαιωνόμαστε ότι το πεδίο status υπάρχει (αν δεν έρχεται από το API)
                    if (!question.status) {
                        question.status = 'active'; // Προεπιλεγμένη τιμή
                    }
                });
                
                console.log('Φορτώθηκαν', questionsData.length, 'ερωτήσεις');
                if (questionsData.length > 0) {
                    console.log('Παράδειγμα δομής ερώτησης:', questionsData[0]);
                }
                
                filteredQuestions = [...questionsData]; // Αρχικά όλες οι ερωτήσεις
                
                // Ενημέρωση των counters
                if (filteredCountElem) {
                    filteredCountElem.textContent = filteredQuestions.length;
                }
                if (totalCountElem) {
                    totalCountElem.textContent = questionsData.length;
                }
                
                renderQuestions(filteredQuestions);
                
                // Μετά τη φόρτωση των ερωτήσεων, φορτώνουμε τις κατηγορίες
                loadCategoriesFromQuestions();
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
    
    // Φόρτωση κατηγοριών από τις ερωτήσεις
    function loadCategoriesFromQuestions() {
        if (!filterCategory || !questionsData.length) return;
        
        // Εξαγωγή μοναδικών κατηγοριών από τις ερωτήσεις
        const uniqueCategories = [...new Set(questionsData.map(q => q.category_name))].sort();
        
        // Καθαρισμός προηγούμενων επιλογών
        filterCategory.innerHTML = '<option value="">Όλες οι κατηγορίες</option>';
        
        // Προσθήκη των κατηγοριών στο dropdown
        uniqueCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            filterCategory.appendChild(option);
        });
        
        console.log('Φορτώθηκαν', uniqueCategories.length, 'κατηγορίες:', uniqueCategories);
    }
    
    // Φόρτωση υποκατηγοριών
    function loadSubcategories() {
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list_subcategories'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                subcategories = data.subcategories;
                console.log('Φορτώθηκαν', subcategories.length, 'υποκατηγορίες');
                
                if (filterSubcategory) {
                    // Καθαρισμός προηγούμενων επιλογών
                    filterSubcategory.innerHTML = '<option value="">Όλες οι υποκατηγορίες</option>';
                    
                    // Προεπιλεγμένα εμφανίζουμε όλες τις υποκατηγορίες
                    subcategories.forEach(subcategory => {
                        const option = document.createElement('option');
                        option.value = subcategory.id;
                        option.textContent = subcategory.name;
                        option.dataset.category = subcategory.category_name;
                        filterSubcategory.appendChild(option);
                    });
                }
            } else {
                console.error('Error loading subcategories:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching subcategories:', error);
        });
    }
    
    // Συμπλήρωση των υποκατηγοριών με βάση την επιλεγμένη κατηγορία
    function populateSubcategories(selectedCategory) {
        if (!filterSubcategory) return;
        
        // Καθαρισμός προηγούμενων επιλογών
        filterSubcategory.innerHTML = '<option value="">Όλες οι υποκατηγορίες</option>';
        
        // Αν δεν επιλέχθηκε κατηγορία, εμφανίζουμε όλες τις υποκατηγορίες
        if (!selectedCategory) {
            subcategories.forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.name;
                option.dataset.category = subcategory.category_name;
                filterSubcategory.appendChild(option);
            });
            return;
        }
        
        // Φιλτράρισμα υποκατηγοριών με βάση την επιλεγμένη κατηγορία
        const filteredSubcategories = subcategories.filter(subcategory => 
            subcategory.category_name === selectedCategory);
        
        console.log(`Βρέθηκαν ${filteredSubcategories.length} υποκατηγορίες για την κατηγορία "${selectedCategory}"`);
        
        filteredSubcategories.forEach(subcategory => {
            const option = document.createElement('option');
            option.value = subcategory.id;
            option.textContent = subcategory.name;
            option.dataset.category = subcategory.category_name;
            filterSubcategory.appendChild(option);
        });
        
        // Επαναφορά του φίλτρου κεφαλαίων μετά την αλλαγή υποκατηγορίας
        if (filterChapter) {
            filterChapter.innerHTML = '<option value="">Όλα τα κεφάλαια</option>';
        }
    }
    
    // Φόρτωση κεφαλαίων με βάση την επιλεγμένη υποκατηγορία
    function populateChapters(subcategoryId) {
        if (!filterChapter) return;
        
        // Επαναφορά σε προεπιλεγμένη κατάσταση
        filterChapter.innerHTML = '<option value="">Όλα τα κεφάλαια</option>';
        
        if (!subcategoryId) return;
        
        console.log(`Φόρτωση κεφαλαίων για υποκατηγορία ID: ${subcategoryId}`);
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=list_chapters&subcategory_id=${subcategoryId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Αποθήκευση των κεφαλαίων για μελλοντική χρήση
                chapters = data.chapters;
                console.log(`Φορτώθηκαν ${chapters.length} κεφάλαια`);
                
                chapters.forEach(chapter => {
                    const option = document.createElement('option');
                    option.value = chapter.id;
                    option.textContent = chapter.name;
                    filterChapter.appendChild(option);
                });
            } else {
                console.error('Error loading chapters:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching chapters:', error);
        });
    }
    
    // Εφαρμογή φίλτρων στα δεδομένα
    function applyFilters() {
        const selectedCategory = filterCategory ? filterCategory.value : '';
        const selectedSubcategory = filterSubcategory ? filterSubcategory.value : '';
        const selectedChapter = filterChapter ? filterChapter.value : '';
        const selectedType = filterType ? filterType.value : '';
        const selectedStatus = filterStatus ? filterStatus.value : '';
        const searchTerm = filterSearch ? filterSearch.value.toLowerCase() : '';
        
        console.log('Εφαρμογή φίλτρων:');
        console.log('- Κατηγορία:', selectedCategory);
        console.log('- Υποκατηγορία ID:', selectedSubcategory);
        console.log('- Κεφάλαιο ID:', selectedChapter);
        console.log('- Τύπος:', selectedType);
        console.log('- Κατάσταση:', selectedStatus);
        console.log('- Αναζήτηση:', searchTerm);
        
        // Φιλτράρισμα των ερωτήσεων
        filteredQuestions = questionsData.filter(question => {
            // Για debugging - δείτε τη δομή της πρώτης ερώτησης 
            if (questionsData.indexOf(question) === 0) {
                console.log('Δομή ερώτησης:', question);
            }
            
            // Φίλτρο κατηγορίας
            if (selectedCategory && question.category_name !== selectedCategory) {
                return false;
            }
            
            // Φίλτρο υποκατηγορίας
            // Ελέγχουμε αν το πεδίο είναι subcategory_id ή subcategory_name
            if (selectedSubcategory) {
                if (question.subcategory_id && question.subcategory_id != selectedSubcategory) {
                    return false;
                } else if (question.subcategory_name) {
                    // Βρίσκουμε την υποκατηγορία από το όνομα
                    const subcatObj = subcategories.find(s => s.id == selectedSubcategory);
                    if (subcatObj && question.subcategory_name !== subcatObj.name) {
                        return false;
                    }
                }
            }
            
            // Φίλτρο κεφαλαίου
            if (selectedChapter) {
                // Ελέγχουμε αν χρησιμοποιείται chapter_id ή chapter_name
                if (question.chapter_id && question.chapter_id != selectedChapter) {
                    return false;
                } else if (question.chapter_name) {
                    const matchingChapterIds = chapters.filter(ch => ch.id == selectedChapter).map(ch => ch.name);
                    if (matchingChapterIds.length > 0 && !matchingChapterIds.includes(question.chapter_name)) {
                        return false;
                    }
                }
            }
            
            // Φίλτρο τύπου ερώτησης
            if (selectedType && question.question_type !== selectedType) {
                return false;
            }
            
            // Φίλτρο κατάστασης
            if (selectedStatus && question.status !== selectedStatus) {
                return false;
            }
            
            // Φίλτρο αναζήτησης
            if (searchTerm) {
                const questionText = question.question_text ? question.question_text.toLowerCase() : '';
                if (!questionText.includes(searchTerm)) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Ενημέρωση των counters
        if (filteredCountElem) {
            filteredCountElem.textContent = filteredQuestions.length;
        }
        if (totalCountElem) {
            totalCountElem.textContent = questionsData.length;
        }
        
        console.log(`Βρέθηκαν ${filteredQuestions.length} από ${questionsData.length} ερωτήσεις`);
        
        // Ενημέρωση του πίνακα με τις φιλτραρισμένες ερωτήσεις
        renderQuestions(filteredQuestions);
    }
    
    // Επαναφορά των φίλτρων στις αρχικές τιμές
    function resetFilters() {
        if (filterCategory) filterCategory.value = '';
        if (filterSubcategory) filterSubcategory.value = '';
        if (filterChapter) filterChapter.value = '';
        if (filterType) filterType.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterSearch) filterSearch.value = '';
        
        // Επαναφορά του dropdown των υποκατηγοριών
        populateSubcategories('');
        
        // Επαναφορά του dropdown των κεφαλαίων
        if (filterChapter) {
            filterChapter.innerHTML = '<option value="">Όλα τα κεφάλαια</option>';
        }
        
        // Επαναφορά των απεικονιζόμενων ερωτήσεων
        filteredQuestions = [...questionsData];
        
        // Ενημέρωση των counters
        if (filteredCountElem) {
            filteredCountElem.textContent = filteredQuestions.length;
        }
        if (totalCountElem) {
            totalCountElem.textContent = questionsData.length;
        }
        
        // Ενημέρωση του πίνακα
        renderQuestions(filteredQuestions);
    }
    
    // ======== Functions για τις Ερωτήσεις ========
    
    // Απεικόνιση των ερωτήσεων στον πίνακα με τις νέες στήλες
function renderQuestions(questions = null) {
    if (!questionsTableBody) return;
    
    // Αν δεν δοθούν ερωτήσεις, χρησιμοποιούμε τις filteredQuestions
    const displayQuestions = questions || filteredQuestions || questionsData;
    
    if (!displayQuestions || !displayQuestions.length) {
        questionsTableBody.innerHTML = '<tr><td colspan="8">Δεν βρέθηκαν ερωτήσεις</td></tr>';
        return;
    }
    
    questionsTableBody.innerHTML = '';
    
    displayQuestions.forEach(question => {
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
        
        // Δημιουργία των νέων στηλών
        row.innerHTML += `
            <td>${htmlEscape(questionText)}</td>
            <td>${htmlEscape(question.category_name)}</td>
            <td>${htmlEscape(question.subcategory_name || '')}</td>
            <td>${htmlEscape(question.chapter_name || '')}</td>
            <td>${question.status === 'active' ? '<span class="badge-active">Ενεργή</span>' : '<span class="badge-inactive">Ανενεργή</span>'}</td>
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
    
    // Ενημέρωση του μετρητή επιλεγμένων ερωτήσεων
function updateBulkSelectionCounter() {
    const counter = document.getElementById('selected-count');
    
    if (counter) {
        counter.textContent = selectedQuestions.size;
    }
}
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
        
        // Προσθήκη των checkbox listeners
        addCheckboxListeners();
    }
    
    // Συνάρτηση για εναλλαγή επιλογής ερώτησης
function toggleQuestionSelection(questionId, isSelected) {
    questionId = parseInt(questionId);
    
    if (isSelected) {
        selectedQuestions.add(questionId);
    } else {
        selectedQuestions.delete(questionId);
    }
    
    console.log(`Το σύνολο των επιλεγμένων ερωτήσεων είναι τώρα: ${selectedQuestions.size}`);
    
    // Ενημέρωση του UI
    updateQuestionRowStyle(questionId, isSelected);
    updateBulkSelectionCounter();
    
    // Εμφάνιση ή απόκρυψη της μπάρας μαζικών ενεργειών
    toggleBulkActionBar();
}

// Ενημέρωση μόνο του μετρητή, όχι της προβολής της μπάρας
function updateBulkSelectionCounter() {
    const counter = document.getElementById('selected-count');
    
    if (counter) {
        counter.textContent = selectedQuestions.size;
    }
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
                filteredQuestions = filteredQuestions.filter(q => q.id != questionId);
                
                // Ενημέρωση των counters
                if (filteredCountElem) {
                    filteredCountElem.textContent = filteredQuestions.length;
                }
                if (totalCountElem) {
                    totalCountElem.textContent = questionsData.length;
                }
                
                renderQuestions(filteredQuestions);
                
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
        window.location.href = 'http://localhost/drivetest/admin/test/edit_question.php?id=' + questionId;
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
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('el-GR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        } catch (e) {
            console.error('Error formatting date:', e);
            return dateString;
        }
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
        if (str === undefined || str === null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    
    // Εξαγωγή των απαραίτητων συναρτήσεων για χρήση εκτός του event listener
    window.bulkDeleteQuestions = bulkDeleteQuestions;
    window.selectAllQuestions = selectAllQuestions;
    window.applyFilters = applyFilters;
    window.resetFilters = resetFilters;
    window.renderFilteredQuestions = renderQuestions;
});
// Διόρθωση της συνάρτησης toggleQuestionSelection
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
    
    // Εμφάνιση ή απόκρυψη της μπάρας μαζικών ενεργειών
    toggleBulkActionBar();
}

// Ξεχωριστή συνάρτηση για εμφάνιση/απόκρυψη της μπάρας μαζικών ενεργειών
function toggleBulkActionBar() {
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    
    if (bulkActionsBar) {
        if (selectedQuestions.size > 0) {
            console.log('Εμφάνιση μπάρας μαζικών ενεργειών');
            bulkActionsBar.style.display = 'flex';
        } else {
            console.log('Απόκρυψη μπάρας μαζικών ενεργειών');
            bulkActionsBar.style.display = 'none';
        }
    } else {
        console.error('Το στοιχείο bulk-actions-bar δεν βρέθηκε!');
    }
}

// Ενημερωμένη συνάρτηση αρχικοποίησης των checkboxes
function initCheckboxListeners() {
    // Master checkbox
    const masterCheckbox = document.getElementById('select-all-questions');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            selectAllQuestions(this.checked);
        });
    }
    
    // Individual checkboxes
    document.querySelectorAll('.question-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const questionId = this.dataset.id;
            toggleQuestionSelection(questionId, this.checked);
        });
    });
}
// Προσθέστε αυτή τη συνάρτηση στο script σας
function setupResizableColumns() {
    const table = document.querySelector('.resizable-table');
    if (!table) return;
    
    const headers = table.querySelectorAll('th');
    const tableWidth = table.offsetWidth;
    
    headers.forEach(header => {
        // Προσθήκη του στοιχείου για το resizing
        const resizer = document.createElement('div');
        resizer.className = 'resizer';
        header.appendChild(resizer);
        
        let startX, startWidth, tableInitialWidth;
        
        const initResize = e => {
            startX = e.pageX;
            startWidth = header.offsetWidth;
            tableInitialWidth = table.offsetWidth;
            
            // Αποτροπή επιλογής κειμένου κατά τη διάρκεια του resizing
            document.body.style.userSelect = 'none';
            
            // Προσθήκη class για το resizing
            resizer.classList.add('resizing');
            
            // Προσθήκη event listeners για το dragging
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
        };
        
        const resize = e => {
            // Υπολογισμός του νέου πλάτους
            const width = startWidth + (e.pageX - startX);
            
            // Ορισμός του ελάχιστου πλάτους
            if (width > 30) {
                header.style.width = `${width}px`;
                // Διατήρηση του συνολικού πλάτους του πίνακα
                table.style.width = tableInitialWidth + 'px';
            }
        };
        
        const stopResize = () => {
            document.body.style.userSelect = '';
            resizer.classList.remove('resizing');
            
            // Αφαίρεση των event listeners
            document.removeEventListener('mousemove', resize);
            document.removeEventListener('mouseup', stopResize);
        };
        
        // Προσθήκη event listener για το mousedown
        resizer.addEventListener('mousedown', initResize);
    });
}

// Κάλεσε αυτή τη συνάρτηση μετά τη φόρτωση της σελίδας και μετά από κάθε ενημέρωση του πίνακα
document.addEventListener('DOMContentLoaded', function() {
    // ... υπάρχον κώδικας ...
    
    // Προσθέστε αυτή τη γραμμή στο τέλος του event listener DOMContentLoaded
    setupResizableColumns();
});

// Απεικόνιση των ερωτήσεων στον πίνακα
function renderQuestions(questions = null) {
    if (!questionsTableBody) return;
    
    // Αν δεν δοθούν ερωτήσεις, χρησιμοποιούμε τις filteredQuestions
    const displayQuestions = questions || filteredQuestions || questionsData;
    
    if (!displayQuestions || !displayQuestions.length) {
        questionsTableBody.innerHTML = '<tr><td colspan="8">Δεν βρέθηκαν ερωτήσεις</td></tr>';
        return;
    }
    
    questionsTableBody.innerHTML = '';
    
    displayQuestions.forEach(question => {
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
        
        // Δημιουργία των στηλών του πίνακα
        row.innerHTML += `
            <td>${htmlEscape(questionText)}</td>
            <td>${htmlEscape(question.category_name || '')}</td>
            <td>${htmlEscape(question.subcategory_name || '')}</td>
            <td>${htmlEscape(question.chapter_name || '')}</td>
            <td>${question.status === 'active' ? '<span class="badge-active">Ενεργή</span>' : '<span class="badge-inactive">Ανενεργή</span>'}</td>
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
    
    // Ελέγχουμε αν υπάρχουν επιλεγμένες ερωτήσεις και εμφανίζουμε τη μπάρα
    toggleBulkActionBar();
    
    // Ρύθμιση των resizable columns
    setupResizableColumns();
}
// Συνάρτηση για τα checkbox των ερωτήσεων - καλείται μετά από κάθε render
function addCheckboxListeners() {
    // Individual question checkboxes
    document.querySelectorAll('.question-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log(`Checkbox με ID ${this.dataset.id} άλλαξε κατάσταση σε: ${this.checked}`);
            toggleQuestionSelection(this.dataset.id, this.checked);
        });
    });
    
    // Master checkbox
    const masterCheckbox = document.getElementById('select-all-questions');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            console.log(`Master checkbox άλλαξε κατάσταση σε: ${this.checked}`);
            selectAllQuestions(this.checked);
        });
    }
}