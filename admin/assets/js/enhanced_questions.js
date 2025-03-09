/**
 * DriveTest Enhanced Questions Manager
 * JavaScript για τη διαχείριση βελτιωμένων ερωτήσεων
 */
document.addEventListener('DOMContentLoaded', function() {
    // ===== Διαχείριση Tabs =====
    initTabs();
    
    // ===== Εναλλαγή τύπου ερώτησης =====
    initQuestionTypeToggle();
    
    // ===== Διαχείριση κατηγοριών/υποκατηγοριών/κεφαλαίων =====
    initCategoryManagement();
    
    // ===== Διαχείριση απαντήσεων =====
    initAnswersManagement();
    
    // ===== Sortable (για ταξινόμηση) =====
    if (typeof jQuery !== 'undefined' && jQuery.ui) {
        initSortableItems();
    }

    // Προσθήκη debug logging για τη φόρμα
    const form = document.querySelector('form');
    if (form) {
        console.log('Form found:', form);
        form.addEventListener('submit', function(event) {
            console.log('Form is being submitted...');
            console.log('Form action:', form.action);
            console.log('Form method:', form.method);
            
            // Έλεγχος για υποχρεωτικά πεδία
            const requiredFields = form.querySelectorAll('[required]');
            let allValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    console.error('Required field empty:', field.name || field.id);
                    allValid = false;
                }
            });
            
            if (!allValid) {
                console.error('Form validation failed');
                // Η φόρμα θα υποβληθεί κανονικά, αλλά θα δούμε το πρόβλημα στην κονσόλα
            }
            
            // Debug: αποφυγή υποβολής για έλεγχο (απενεργοποιημένο)
            // event.preventDefault();
            // console.log('Form data:', new FormData(form));
        });
    } else {
        console.error('Form not found!');
    }
});

/**
 * Αρχικοποίηση των tabs
 */
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Απενεργοποίηση όλων των tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Ενεργοποίηση του επιλεγμένου tab
            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });
}

/**
 * Αρχικοποίηση της εναλλαγής τύπου ερώτησης
 */
function initQuestionTypeToggle() {
    const questionTypeSelect = document.getElementById('question_type');
    const answerTypes = [
        'single_choice_answers', 
        'multiple_choice_answers', 
        'true_false_answers', 
        'fill_in_blank_answers', 
        'matching_answers', 
        'ordering_answers', 
        'short_answer_answers', 
        'essay_answers'
    ];
    
    if (questionTypeSelect) {
        // Αρχικοποίηση - εμφάνιση του σωστού τύπου απαντήσεων βάσει της αρχικής επιλογής
        toggleAnswerType(questionTypeSelect.value);
        
        // Προσθήκη listener για αλλαγές
        questionTypeSelect.addEventListener('change', function() {
            toggleAnswerType(this.value);
        });
    }
    
    /**
     * Εναλλαγή του τύπου απαντήσεων βάσει του επιλεγμένου τύπου ερώτησης
     */
    function toggleAnswerType(questionType) {
        // Απόκρυψη όλων των τύπων απαντήσεων
        answerTypes.forEach(type => {
            const element = document.getElementById(type);
            if (element) {
                element.style.display = 'none';
            }
        });
        
        // Εμφάνιση του επιλεγμένου τύπου
        const selectedType = document.getElementById(questionType + '_answers');
        if (selectedType) {
            selectedType.style.display = 'block';
        }
    }
}

/**
 * Αρχικοποίηση της διαχείρισης κατηγοριών
 */
function initCategoryManagement() {
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const chapterSelect = document.getElementById('chapter_id');
    
    const addCategoryBtn = document.getElementById('add-category-btn');
    const addSubcategoryBtn = document.getElementById('add-subcategory-btn');
    const addChapterBtn = document.getElementById('add-chapter-btn');
    
    const newCategoryFields = document.getElementById('new-category-fields');
    const newSubcategoryFields = document.getElementById('new-subcategory-fields');
    const newChapterFields = document.getElementById('new-chapter-fields');
    
    // Αποθήκευση των αρχικών δεδομένων υποκατηγοριών και κεφαλαίων
    const subcategoriesData = {};
    const chaptersData = {};
    
    // Αρχικοποίηση των υποκατηγοριών από το server
    const subcategories = document.querySelectorAll('#subcategory_id option');
    subcategories.forEach(option => {
        const categoryId = option.getAttribute('data-category');
        if (categoryId) {
            if (!subcategoriesData[categoryId]) {
                subcategoriesData[categoryId] = [];
            }
            subcategoriesData[categoryId].push({
                id: option.value,
                name: option.innerText
            });
        }
    });
    
    // Αρχικοποίηση των κεφαλαίων από το server
    const chapters = document.querySelectorAll('#chapter_id option');
    chapters.forEach(option => {
        const subcategoryId = option.getAttribute('data-subcategory');
        if (subcategoryId) {
            if (!chaptersData[subcategoryId]) {
                chaptersData[subcategoryId] = [];
            }
            chaptersData[subcategoryId].push({
                id: option.value,
                name: option.innerText
            });
        }
    });
    
    // Event listener για επιλογή κατηγορίας
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            
            if (categoryId === 'new') {
                // Εμφάνιση πεδίων για νέα κατηγορία
                newCategoryFields.classList.remove('hidden');
                // Απενεργοποίηση της επιλογής υποκατηγορίας και κεφαλαίου
                subcategorySelect.disabled = true;
                chapterSelect.disabled = true;
                
                // Ενεργοποίηση των κουμπιών προσθήκης
                addSubcategoryBtn.disabled = false;
                addChapterBtn.disabled = false;
            } else if (categoryId) {
                // Απόκρυψη πεδίων νέας κατηγορίας
                newCategoryFields.classList.add('hidden');
                
                // Ενημέρωση των υποκατηγοριών
                updateSubcategories(categoryId);
                
                // Ενεργοποίηση της επιλογής υποκατηγορίας
                subcategorySelect.disabled = false;
                addSubcategoryBtn.disabled = false;
                
                // Αν δεν υπάρχουν υποκατηγορίες, ενεργοποίηση του κουμπιού προσθήκης
                if (subcategorySelect.options.length <= 2) { // 1 για default + 1 για new
                    addSubcategoryBtn.click();
                }
            } else {
                // Αν δεν έχει επιλεγεί κατηγορία
                newCategoryFields.classList.add('hidden');
                subcategorySelect.disabled = true;
                chapterSelect.disabled = true;
                addSubcategoryBtn.disabled = true;
                addChapterBtn.disabled = true;
            }
        });
    }
    
    // Event listener για επιλογή υποκατηγορίας
    if (subcategorySelect) {
        subcategorySelect.addEventListener('change', function() {
            const subcategoryId = this.value;
            
            if (subcategoryId === 'new') {
                // Εμφάνιση πεδίων για νέα υποκατηγορία
                newSubcategoryFields.classList.remove('hidden');
                // Απενεργοποίηση της επιλογής κεφαλαίου
                chapterSelect.disabled = true;
                
                // Ενεργοποίηση του κουμπιού προσθήκης κεφαλαίου
                addChapterBtn.disabled = false;
            } else if (subcategoryId) {
                // Απόκρυψη πεδίων νέας υποκατηγορίας
                newSubcategoryFields.classList.add('hidden');
                
                // Ενημέρωση των κεφαλαίων
                updateChapters(subcategoryId);
                
                // Ενεργοποίηση της επιλογής κεφαλαίου
                chapterSelect.disabled = false;
                addChapterBtn.disabled = false;
                
                // Αν δεν υπάρχουν κεφάλαια, ενεργοποίηση του κουμπιού προσθήκης
                if (chapterSelect.options.length <= 2) { // 1 για default + 1 για new
                    addChapterBtn.click();
                }
            } else {
                // Αν δεν έχει επιλεγεί υποκατηγορία
                newSubcategoryFields.classList.add('hidden');
                chapterSelect.disabled = true;
                addChapterBtn.disabled = true;
            }
        });
    }
    
    // Event listener για επιλογή κεφαλαίου
    if (chapterSelect) {
        chapterSelect.addEventListener('change', function() {
            const chapterId = this.value;
            
            if (chapterId === 'new') {
                // Εμφάνιση πεδίων για νέο κεφάλαιο
                newChapterFields.classList.remove('hidden');
            } else {
                // Απόκρυψη πεδίων νέου κεφαλαίου
                newChapterFields.classList.add('hidden');
            }
        });
    }
    
    // Event listener για το κουμπί προσθήκης κατηγορίας
    if (addCategoryBtn) {
        addCategoryBtn.addEventListener('click', function() {
            categorySelect.value = 'new';
            newCategoryFields.classList.remove('hidden');
            // Trigger change event
            const event = new Event('change');
            categorySelect.dispatchEvent(event);
        });
    }
    
    // Event listener για το κουμπί προσθήκης υποκατηγορίας
    if (addSubcategoryBtn) {
        addSubcategoryBtn.addEventListener('click', function() {
            if (subcategorySelect.disabled) return;
            
            subcategorySelect.value = 'new';
            newSubcategoryFields.classList.remove('hidden');
            // Trigger change event
            const event = new Event('change');
            subcategorySelect.dispatchEvent(event);
        });
    }
    
    // Event listener για το κουμπί προσθήκης κεφαλαίου
    if (addChapterBtn) {
        addChapterBtn.addEventListener('click', function() {
            if (chapterSelect.disabled) return;
            
            chapterSelect.value = 'new';
            newChapterFields.classList.remove('hidden');
            // Trigger change event
            const event = new Event('change');
            chapterSelect.dispatchEvent(event);
        });
    }
    
    /**
     * Ενημέρωση του dropdown υποκατηγοριών βάσει της επιλεγμένης κατηγορίας
     */
    function updateSubcategories(categoryId) {
        // Καθαρισμός του dropdown
        subcategorySelect.innerHTML = '';
        
        // Προσθήκη της προεπιλεγμένης επιλογής
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.text = '-- Επιλέξτε Υποκατηγορία --';
        subcategorySelect.appendChild(defaultOption);
        
        // Φιλτράρισμα και προσθήκη των υποκατηγοριών που ανήκουν στην επιλεγμένη κατηγορία
        if (subcategoriesData[categoryId]) {
            subcategoriesData[categoryId].forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.text = subcategory.name;
                option.setAttribute('data-category', categoryId);
                subcategorySelect.appendChild(option);
            });
        }
        
        // Προσθήκη της επιλογής "Νέα Υποκατηγορία"
        const newOption = document.createElement('option');
        newOption.value = 'new';
        newOption.text = '+ Νέα Υποκατηγορία';
        subcategorySelect.appendChild(newOption);
        
        // Απενεργοποίηση του dropdown κεφαλαίων
        chapterSelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>';
        chapterSelect.disabled = true;
        newChapterFields.classList.add('hidden');
    }
    
    /**
     * Ενημέρωση του dropdown κεφαλαίων βάσει της επιλεγμένης υποκατηγορίας
     */
    function updateChapters(subcategoryId) {
        // Καθαρισμός του dropdown
        chapterSelect.innerHTML = '';
        
        // Προσθήκη της προεπιλεγμένης επιλογής
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.text = '-- Επιλέξτε Κεφάλαιο --';
        chapterSelect.appendChild(defaultOption);
        
        // Φιλτράρισμα και προσθήκη των κεφαλαίων που ανήκουν στην επιλεγμένη υποκατηγορία
        if (chaptersData[subcategoryId]) {
            chaptersData[subcategoryId].forEach(chapter => {
                const option = document.createElement('option');
                option.value = chapter.id;
                option.text = chapter.name;
                option.setAttribute('data-subcategory', subcategoryId);
                chapterSelect.appendChild(option);
            });
        }
        
        // Προσθήκη της επιλογής "Νέο Κεφάλαιο"
        const newOption = document.createElement('option');
        newOption.value = 'new';
        newOption.text = '+ Νέο Κεφάλαιο';
        chapterSelect.appendChild(newOption);
    }
    
    // Φόρτωση υποκατηγοριών και κεφαλαίων με AJAX αν δεν έχουν προ-φορτωθεί
    if (Object.keys(subcategoriesData).length === 0) {
        loadSubcategoriesAjax();
    }
    
    /**
     * Φόρτωση υποκατηγοριών με AJAX
     */
    function loadSubcategoriesAjax() {
        fetch('question_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list_subcategories'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Οργάνωση υποκατηγοριών ανά κατηγορία
                data.subcategories.forEach(subcategory => {
                    const categoryId = subcategory.test_category_id;
                    if (!subcategoriesData[categoryId]) {
                        subcategoriesData[categoryId] = [];
                    }
                    subcategoriesData[categoryId].push({
                        id: subcategory.id,
                        name: subcategory.name
                    });
                });
                
                // Αν υπάρχει επιλεγμένη κατηγορία, ενημέρωση των υποκατηγοριών
                if (categorySelect.value && categorySelect.value !== 'new') {
                    updateSubcategories(categorySelect.value);
                }
            }
        })
        .catch(error => console.error('Error loading subcategories:', error));
    }
    
    /**
     * Φόρτωση κεφαλαίων με AJAX για συγκεκριμένη υποκατηγορία
     */
    function loadChaptersAjax(subcategoryId) {
        if (!subcategoryId || subcategoryId === 'new') return;
        
        fetch('question_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list_chapters&subcategory_id=' + subcategoryId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Αποθήκευση των κεφαλαίων για την υποκατηγορία
                chaptersData[subcategoryId] = data.chapters;
                
                // Ενημέρωση του dropdown
                updateChapters(subcategoryId);
            }
        })
        .catch(error => console.error('Error loading chapters:', error));
    }
}

/**
 * Αρχικοποίηση της διαχείρισης απαντήσεων
 */
function initAnswersManagement() {
    // Προσθήκη απάντησης σε single/multiple choice
    const addAnswerButtons = document.querySelectorAll('.add-answer-btn');
    addAnswerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const container = document.getElementById(targetId);
            
            if (container) {
                const newIndex = container.querySelectorAll('.answer-entry').length;
                const answerEntry = document.createElement('div');
                answerEntry.className = 'answer-entry';
                
                // Δημιουργία του νέου πεδίου απάντησης με βάση το target
                if (targetId === 'single_choice_container' || targetId === 'multiple_choice_container') {
                    answerEntry.innerHTML = `
                        <input type="text" name="answers[]" placeholder="Απάντηση ${newIndex + 1}" class="answer-text">
                        <input type="checkbox" name="correct_answers[]" value="${newIndex}" class="answer-correct">
                        <label>Σωστή</label>
                        <button type="button" class="remove-answer">❌</button>
                    `;
                } else if (targetId === 'fill_in_blank_container') {
                    answerEntry.innerHTML = `
                        <label>Κενό #${newIndex + 1}:</label>
                        <input type="text" name="blank_answers[]" placeholder="Αποδεκτή απάντηση" class="answer-text">
                        <button type="button" class="remove-answer">❌</button>
                    `;
                }
                
                container.appendChild(answerEntry);
                
                // Προσθήκη του event listener για διαγραφή
                const removeButton = answerEntry.querySelector('.remove-answer');
                if (removeButton) {
                    removeButton.addEventListener('click', function() {
                        answerEntry.remove();
                        updateAnswerIndices(container);
                    });
                }
            }
        });
    });
    
    // Event listeners για υπάρχοντα κουμπιά διαγραφής απαντήσεων
    const removeAnswerButtons = document.querySelectorAll('.remove-answer');
    removeAnswerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const answerEntry = this.closest('.answer-entry');
            const container = answerEntry.parentElement;
            answerEntry.remove();
            updateAnswerIndices(container);
        });
    });
    
    // Προσθήκη ζεύγους αντιστοίχισης
    const addMatchingPairButton = document.querySelector('.add-matching-pair');
    if (addMatchingPairButton) {
        addMatchingPairButton.addEventListener('click', function() {
            const container = document.getElementById('matching_container');
            const newIndex = container.querySelectorAll('.matching-pair').length;
            
            const pairElement = document.createElement('div');
            pairElement.className = 'matching-pair';
            pairElement.innerHTML = `
                <div class="matching-left">
                    <input type="text" name="matching_left[]" placeholder="Στοιχείο αριστερά" class="answer-text">
                </div>
                <div class="matching-connector">⟷</div>
                <div class="matching-right">
                    <input type="text" name="matching_right[]" placeholder="Στοιχείο δεξιά" class="answer-text">
                </div>
                <button type="button" class="remove-pair">❌</button>
            `;
            
            container.appendChild(pairElement);
            
            // Προσθήκη του event listener για διαγραφή
            const removeButton = pairElement.querySelector('.remove-pair');
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    pairElement.remove();
                });
            }
        });
    }
    
    // Προσθήκη στοιχείου ταξινόμησης
    const addOrderingItemButton = document.querySelector('.add-ordering-item');
    if (addOrderingItemButton) {
        addOrderingItemButton.addEventListener('click', function() {
            const container = document.getElementById('ordering_container');
            const newIndex = container.querySelectorAll('.ordering-item').length;
            
            const itemElement = document.createElement('div');
            itemElement.className = 'ordering-item';
            itemElement.innerHTML = `
                <span class="drag-handle">⋮⋮</span>
                <input type="text" name="ordering_items[]" placeholder="Στοιχείο ${newIndex + 1}" class="answer-text">
                <button type="button" class="remove-item">❌</button>
            `;
            
            container.appendChild(itemElement);
            
            // Προσθήκη του event listener για διαγραφή
            const removeButton = itemElement.querySelector('.remove-item');
            if (removeButton) {
                removeButton.addEventListener('click', function() {
                    itemElement.remove();
                    updateItemIndices(container, 'στοιχείο');
                });
            }
            
            // Αν υπάρχει το jQuery UI, κάνουμε το νέο στοιχείο sortable
            if (typeof jQuery !== 'undefined' && jQuery.ui) {
                jQuery(container).sortable('refresh');
            }
        });
    }
    
    // Event listeners για υπάρχοντα κουμπιά διαγραφής αντιστοίχισης
    const removePairButtons = document.querySelectorAll('.remove-pair');
    removePairButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.matching-pair').remove();
        });
    });
    
    // Event listeners για υπάρχοντα κουμπιά διαγραφής στοιχείων ταξινόμησης
    const removeItemButtons = document.querySelectorAll('.remove-item');
    removeItemButtons.forEach(button => {
        button.addEventListener('click', function() {
            const item = this.closest('.ordering-item');
            const container = item.parentElement;
            item.remove();
            updateItemIndices(container, 'στοιχείο');
        });
    });
    
    /**
     * Ενημέρωση των δεικτών των απαντήσεων
     */
    function updateAnswerIndices(container) {
        const entries = container.querySelectorAll('.answer-entry');
        
        entries.forEach((entry, index) => {
            // Ενημέρωση των placeholders
            const inputText = entry.querySelector('.answer-text');
            if (inputText && inputText.hasAttribute('placeholder')) {
                const placeholderText = inputText.getAttribute('placeholder');
                if (placeholderText.includes('Απάντηση')) {
                    inputText.setAttribute('placeholder', `Απάντηση ${index + 1}`);
                } else if (placeholderText.includes('Κενό')) {
                    const label = entry.querySelector('label');
                    if (label) {
                        label.textContent = `Κενό #${index + 1}:`;
                    }
                }
            }
            
            // Ενημέρωση των τιμών των checkboxes
            const checkbox = entry.querySelector('.answer-correct');
            if (checkbox) {
                checkbox.value = index;
            }
        });
    }
    
    /**
     * Ενημέρωση των δεικτών των στοιχείων
     */
    function updateItemIndices(container, itemText) {
        const items = container.querySelectorAll('.ordering-item');
        
        items.forEach((item, index) => {
            const inputText = item.querySelector('.answer-text');
            if (inputText && inputText.hasAttribute('placeholder')) {
                inputText.setAttribute('placeholder', `${itemText} ${index + 1}`);
            }
        });
    }
}

/**
 * Αρχικοποίηση των sortable στοιχείων
 */
function initSortableItems() {
    if (jQuery('#ordering_container').length) {
        jQuery('#ordering_container').sortable({
            handle: '.drag-handle',
            axis: 'y',
            update: function(event, ui) {
                // Ενημέρωση των δεικτών μετά από drag and drop
                updateItemIndices(this, 'στοιχείο');
            }
        });
    }
    
    /**
     * Ενημέρωση των δεικτών των στοιχείων
     */
    function updateItemIndices(container, itemText) {
        const items = jQuery(container).find('.ordering-item');
        
        items.each(function(index) {
            const inputText = jQuery(this).find('.answer-text');
            if (inputText.length && inputText.attr('placeholder')) {
                inputText.attr('placeholder', `${itemText} ${index + 1}`);
            }
        });
    }
}