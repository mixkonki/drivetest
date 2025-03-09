/**
 * DriveTest - Βελτιωμένη διαχείριση κατηγοριοποίησης για Μαζική Εισαγωγή
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log("✅ Κατηγοριοποίηση JS φορτώθηκε");
    
    // Διαχείριση επιλογών κατηγοριοποίησης
    initCategorizationOptions();
    
    // Διαχείριση αλυσιδωτών dropdowns κατηγοριών
    initCategoryChain();
    
    // Προσθήκη υποστήριξης για δημιουργία νέων στοιχείων
    initNewItemCreation();
    
    // Διαχείριση μεταφοράς αρχείου στο κρυφό input
    initFileTransfer();
    
    // Επικύρωση φόρμας πριν την υποβολή
    initFormValidation();
});

/**
 * Αρχικοποίηση των επιλογών κατηγοριοποίησης (από CSV ή χειροκίνητη επιλογή)
 */
function initCategorizationOptions() {
    const useCSVYes = document.getElementById('use_csv_yes');
    const useCSVNo = document.getElementById('use_csv_no');
    const categoryFields = document.getElementById('category-selection-fields');
    
    if (!useCSVYes || !useCSVNo || !categoryFields) {
        console.log("❗ Δεν βρέθηκαν όλα τα στοιχεία κατηγοριοποίησης");
        return;
    }
    
    console.log("✅ Αρχικοποίηση επιλογών κατηγοριοποίησης");
    
    // Προσθήκη listener για την επιλογή "Χρήση στηλών από το CSV"
    useCSVYes.addEventListener('change', function() {
        if (this.checked) {
            console.log("🔄 Επιλέχθηκε: Χρήση στηλών από το CSV");
            categoryFields.style.display = 'none';
            
            // Απενεργοποίηση των required attributes στα πεδία επιλογής
            toggleRequiredAttributes(false);
        }
    });
    
    // Προσθήκη listener για την επιλογή "Επιλογή κατηγορίας, υποκατηγορίας και κεφαλαίου"
    useCSVNo.addEventListener('change', function() {
        if (this.checked) {
            console.log("🔄 Επιλέχθηκε: Χειροκίνητη επιλογή κατηγοριών");
            categoryFields.style.display = 'block';
            
            // Ενεργοποίηση των required attributes στα πεδία επιλογής
            toggleRequiredAttributes(true);
        }
    });
    
    // Αρχικοποίηση των επιλογών με βάση την τρέχουσα κατάσταση
    if (useCSVYes.checked) {
        categoryFields.style.display = 'none';
        toggleRequiredAttributes(false);
    } else {
        categoryFields.style.display = 'block';
        toggleRequiredAttributes(true);
    }
    
    /**
     * Ενεργοποίηση/απενεργοποίηση των required attributes στα πεδία επιλογής
     */
    function toggleRequiredAttributes(enable) {
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        const chapterSelect = document.getElementById('chapter_id');
        
        if (categorySelect) categorySelect.required = enable;
        if (subcategorySelect) subcategorySelect.required = enable;
        if (chapterSelect) chapterSelect.required = enable;
    }
}

/**
 * Αρχικοποίηση των αλυσιδωτών dropdown κατηγοριών
 */
function initCategoryChain() {
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const chapterSelect = document.getElementById('chapter_id');
    
    if (!categorySelect || !subcategorySelect || !chapterSelect) {
        console.log("❗ Δεν βρέθηκαν όλα τα dropdown κατηγοριών");
        return;
    }
    
    console.log("✅ Αρχικοποίηση αλυσιδωτών dropdown");
    
    // Δημιουργία χάρτη υποκατηγοριών ανά κατηγορία
    const subcategoriesByCategory = {};
    document.querySelectorAll('#subcategory_id > option[data-category]').forEach(option => {
        const categoryId = option.getAttribute('data-category');
        if (!subcategoriesByCategory[categoryId]) {
            subcategoriesByCategory[categoryId] = [];
        }
        subcategoriesByCategory[categoryId].push({
            value: option.value,
            text: option.textContent
        });
    });
    
    // Δημιουργία χάρτη κεφαλαίων ανά υποκατηγορία
    const chaptersBySubcategory = {};
    document.querySelectorAll('#chapter_id > option[data-subcategory]').forEach(option => {
        const subcategoryId = option.getAttribute('data-subcategory');
        if (!chaptersBySubcategory[subcategoryId]) {
            chaptersBySubcategory[subcategoryId] = [];
        }
        chaptersBySubcategory[subcategoryId].push({
            value: option.value,
            text: option.textContent
        });
    });
    
    // Προσθήκη listener για αλλαγή στο dropdown κατηγοριών
    categorySelect.addEventListener('change', function() {
        const categoryId = this.value;
        
        // Καθαρισμός και απενεργοποίηση των εξαρτημένων dropdown
        clearAndDisableSelect(subcategorySelect, '-- Επιλέξτε Υποκατηγορία --');
        clearAndDisableSelect(chapterSelect, '-- Επιλέξτε πρώτα Υποκατηγορία --');
        
        if (categoryId) {
            // Ενεργοποίηση του dropdown υποκατηγοριών
            subcategorySelect.disabled = false;
            
            // Προσθήκη των υποκατηγοριών που ανήκουν στην επιλεγμένη κατηγορία
            const subcategories = subcategoriesByCategory[categoryId] || [];
            
            subcategories.forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                option.setAttribute('data-category', categoryId);
                subcategorySelect.appendChild(option);
            });
            
            // Προσθήκη της επιλογής "Νέα Υποκατηγορία"
            const newOption = document.createElement('option');
            newOption.value = "new";
            newOption.textContent = "+ Δημιουργία Νέας Υποκατηγορίας";
            newOption.setAttribute('class', 'new-item-option');
            subcategorySelect.appendChild(newOption);
        }
    });
    
    // Προσθήκη listener για αλλαγή στο dropdown υποκατηγοριών
    subcategorySelect.addEventListener('change', function() {
        const subcategoryId = this.value;
        
        // Καθαρισμός και απενεργοποίηση του dropdown κεφαλαίων
        clearAndDisableSelect(chapterSelect, '-- Επιλέξτε Κεφάλαιο --');
        
        // Έλεγχος αν επιλέχθηκε "Νέα Υποκατηγορία"
        if (subcategoryId === "new") {
            showNewItemForm('subcategory');
            return;
        }
        
        // Απόκρυψη της φόρμας δημιουργίας νέας υποκατηγορίας αν είναι ορατή
        const newSubcategoryForm = document.getElementById('new-subcategory-form');
        if (newSubcategoryForm) {
            newSubcategoryForm.style.display = 'none';
        }
        
        if (subcategoryId) {
            // Ενεργοποίηση του dropdown κεφαλαίων
            chapterSelect.disabled = false;
            
            // Προσθήκη των κεφαλαίων που ανήκουν στην επιλεγμένη υποκατηγορία
            const chapters = chaptersBySubcategory[subcategoryId] || [];
            
            chapters.forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                option.setAttribute('data-subcategory', subcategoryId);
                chapterSelect.appendChild(option);
            });
            
            // Προσθήκη της επιλογής "Νέο Κεφάλαιο"
            const newOption = document.createElement('option');
            newOption.value = "new";
            newOption.textContent = "+ Δημιουργία Νέου Κεφαλαίου";
            newOption.setAttribute('class', 'new-item-option');
            chapterSelect.appendChild(newOption);
        }
    });
    
    // Προσθήκη listener για αλλαγή στο dropdown κεφαλαίων
    chapterSelect.addEventListener('change', function() {
        const chapterId = this.value;
        
        // Έλεγχος αν επιλέχθηκε "Νέο Κεφάλαιο"
        if (chapterId === "new") {
            showNewItemForm('chapter');
        } else {
            // Απόκρυψη της φόρμας δημιουργίας νέου κεφαλαίου αν είναι ορατή
            const newChapterForm = document.getElementById('new-chapter-form');
            if (newChapterForm) {
                newChapterForm.style.display = 'none';
            }
        }
    });
    
    // Βοηθητική συνάρτηση για καθαρισμό και απενεργοποίηση ενός dropdown
    function clearAndDisableSelect(select, defaultOptionText) {
        if (!select) return;
        
        select.innerHTML = '';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = defaultOptionText;
        select.appendChild(defaultOption);
        
        select.disabled = true;
    }
}

/**
 * Αρχικοποίηση της υποστήριξης για δημιουργία νέων στοιχείων
 */
function initNewItemCreation() {
    // Προσθήκη των φορμών για δημιουργία νέων στοιχείων αν δεν υπάρχουν ήδη
    const categoryFields = document.getElementById('category-selection-fields');
    
    if (!categoryFields) {
        console.log("❗ Δεν βρέθηκε το container των πεδίων κατηγοριών");
        return;
    }
    
    console.log("✅ Αρχικοποίηση υποστήριξης για δημιουργία νέων στοιχείων");
    
    // Δημιουργία φόρμας για νέα υποκατηγορία αν δεν υπάρχει
    if (!document.getElementById('new-subcategory-form')) {
        const newSubcategoryForm = document.createElement('div');
        newSubcategoryForm.id = 'new-subcategory-form';
        newSubcategoryForm.style.display = 'none';
        newSubcategoryForm.className = 'new-item-form';
        newSubcategoryForm.innerHTML = `
            <div class="form-group">
                <label for="new_subcategory_name">Όνομα Νέας Υποκατηγορίας:</label>
                <div class="input-with-buttons">
                    <input type="text" id="new_subcategory_name" name="new_subcategory_name" placeholder="Πληκτρολογήστε το όνομα της νέας υποκατηγορίας">
                    <button type="button" class="btn-mini save-new-item" data-type="subcategory">Αποθήκευση</button>
                    <button type="button" class="btn-mini cancel-new-item" data-type="subcategory">Ακύρωση</button>
                </div>
            </div>
        `;
        categoryFields.appendChild(newSubcategoryForm);
        
        // Προσθήκη listeners για τα κουμπιά της φόρμας
        newSubcategoryForm.querySelector('.save-new-item').addEventListener('click', function() {
            saveNewItem('subcategory');
        });
        
        newSubcategoryForm.querySelector('.cancel-new-item').addEventListener('click', function() {
            cancelNewItem('subcategory');
        });
    }
    
    // Δημιουργία φόρμας για νέο κεφάλαιο αν δεν υπάρχει
    if (!document.getElementById('new-chapter-form')) {
        const newChapterForm = document.createElement('div');
        newChapterForm.id = 'new-chapter-form';
        newChapterForm.style.display = 'none';
        newChapterForm.className = 'new-item-form';
        newChapterForm.innerHTML = `
            <div class="form-group">
                <label for="new_chapter_name">Όνομα Νέου Κεφαλαίου:</label>
                <div class="input-with-buttons">
                    <input type="text" id="new_chapter_name" name="new_chapter_name" placeholder="Πληκτρολογήστε το όνομα του νέου κεφαλαίου">
                    <button type="button" class="btn-mini save-new-item" data-type="chapter">Αποθήκευση</button>
                    <button type="button" class="btn-mini cancel-new-item" data-type="chapter">Ακύρωση</button>
                </div>
            </div>
        `;
        categoryFields.appendChild(newChapterForm);
        
        // Προσθήκη listeners για τα κουμπιά της φόρμας
        newChapterForm.querySelector('.save-new-item').addEventListener('click', function() {
            saveNewItem('chapter');
        });
        
        newChapterForm.querySelector('.cancel-new-item').addEventListener('click', function() {
            cancelNewItem('chapter');
        });
    }
    
    // Προσθήκη CSS για τις φόρμες δημιουργίας νέων στοιχείων αν δεν υπάρχει ήδη link για το CSS
    if (!document.querySelector('link[href*="categorization_styles.css"]')) {
        const style = document.createElement('style');
        style.textContent = `
            .new-item-form {
                margin-top: 10px;
                padding: 15px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            
            .input-with-buttons {
                display: flex;
                gap: 10px;
            }
            
            .input-with-buttons input {
                flex: 1;
            }
            
            .btn-mini {
                padding: 5px 10px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                font-weight: bold;
            }
            
            .save-new-item {
                background-color: #28a745;
                color: white;
            }
            
            .cancel-new-item {
                background-color: #6c757d;
                color: white;
            }
            
            .new-item-option {
                font-style: italic;
                color: #007bff;
                background-color: #f0f8ff;
            }
            
            .btn-mini:hover {
                opacity: 0.9;
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Εμφάνιση της φόρμας δημιουργίας νέου στοιχείου
 * @param {string} type - Ο τύπος του στοιχείου ('subcategory' ή 'chapter')
 */
function showNewItemForm(type) {
    console.log(`🔄 Εμφάνιση φόρμας για νέο ${type}`);
    
    if (type === 'subcategory') {
        const newSubcategoryForm = document.getElementById('new-subcategory-form');
        if (newSubcategoryForm) {
            newSubcategoryForm.style.display = 'block';
            document.getElementById('new_subcategory_name').focus();
        }
    } else if (type === 'chapter') {
        const newChapterForm = document.getElementById('new-chapter-form');
        if (newChapterForm) {
            newChapterForm.style.display = 'block';
            document.getElementById('new_chapter_name').focus();
        }
    }
}

/**
 * Αποθήκευση νέου στοιχείου
 * @param {string} type - Ο τύπος του στοιχείου ('subcategory' ή 'chapter')
 */
function saveNewItem(type) {
    console.log(`🔄 Αποθήκευση νέου ${type}`);
    
    if (type === 'subcategory') {
        const newSubcategoryName = document.getElementById('new_subcategory_name').value.trim();
        if (!newSubcategoryName) {
            alert('Παρακαλώ εισάγετε όνομα για τη νέα υποκατηγορία.');
            return;
        }
        
        const categoryId = document.getElementById('category_id').value;
        if (!categoryId) {
            alert('Παρακαλώ επιλέξτε πρώτα κατηγορία.');
            return;
        }
        
        // Προσθήκη της νέας υποκατηγορίας στο dropdown
        const subcategorySelect = document.getElementById('subcategory_id');
        const newOption = document.createElement('option');
        
        // Χρήση προσωρινού ID για τη νέα υποκατηγορία
        const tempId = 'new_' + Date.now();
        newOption.value = tempId;
        newOption.textContent = newSubcategoryName;
        newOption.setAttribute('data-category', categoryId);
        newOption.setAttribute('data-is-new', 'true');
        
        // Προσθήκη πριν από την επιλογή "Νέα Υποκατηγορία"
        const newItemOption = subcategorySelect.querySelector('.new-item-option');
        subcategorySelect.insertBefore(newOption, newItemOption);
        
        // Επιλογή της νέας υποκατηγορίας
        subcategorySelect.value = tempId;
        
        // Προσθήκη ενός κρυφού πεδίου για το όνομα της νέας υποκατηγορίας
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'new_subcategory_names[' + tempId + ']';
        hiddenInput.value = newSubcategoryName;
        document.querySelector('form').appendChild(hiddenInput);
        
        // Απόκρυψη της φόρμας δημιουργίας
        document.getElementById('new-subcategory-form').style.display = 'none';
        
        // Ενεργοποίηση του dropdown κεφαλαίων
        const chapterSelect = document.getElementById('chapter_id');
        chapterSelect.disabled = false;
        
        // Καθαρισμός του dropdown κεφαλαίων
        chapterSelect.innerHTML = '';
        
        // Προσθήκη της προεπιλεγμένης επιλογής
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Επιλέξτε Κεφάλαιο --';
        chapterSelect.appendChild(defaultOption);
        
        // Προσθήκη της επιλογής "Νέο Κεφάλαιο"
        const newChapterOption = document.createElement('option');
        newChapterOption.value = "new";
        newChapterOption.textContent = "+ Δημιουργία Νέου Κεφαλαίου";
        newChapterOption.setAttribute('class', 'new-item-option');
        chapterSelect.appendChild(newChapterOption);
        
        // Πυροδότηση του event change για να ενημερωθούν άλλα components
        const event = new Event('change');
        subcategorySelect.dispatchEvent(event);
    } else if (type === 'chapter') {
        const newChapterName = document.getElementById('new_chapter_name').value.trim();
        if (!newChapterName) {
            alert('Παρακαλώ εισάγετε όνομα για το νέο κεφάλαιο.');
            return;
        }
        
        const subcategoryId = document.getElementById('subcategory_id').value;
        if (!subcategoryId) {
            alert('Παρακαλώ επιλέξτε πρώτα υποκατηγορία.');
            return;
        }
        
        // Προσθήκη του νέου κεφαλαίου στο dropdown
        const chapterSelect = document.getElementById('chapter_id');
        const newOption = document.createElement('option');
        
        // Χρήση προσωρινού ID για το νέο κεφάλαιο
        const tempId = 'new_' + Date.now();
        newOption.value = tempId;
        newOption.textContent = newChapterName;
        newOption.setAttribute('data-subcategory', subcategoryId);
        newOption.setAttribute('data-is-new', 'true');
        
        // Προσθήκη πριν από την επιλογή "Νέο Κεφάλαιο"
        const newItemOption = chapterSelect.querySelector('.new-item-option');
        chapterSelect.insertBefore(newOption, newItemOption);
        
        // Επιλογή του νέου κεφαλαίου
        chapterSelect.value = tempId;
        
        // Προσθήκη ενός κρυφού πεδίου για το όνομα του νέου κεφαλαίου
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'new_chapter_names[' + tempId + ']';
        hiddenInput.value = newChapterName;
        document.querySelector('form').appendChild(hiddenInput);
        
        // Απόκρυψη της φόρμας δημιουργίας
        document.getElementById('new-chapter-form').style.display = 'none';
        
        // Πυροδότηση του event change για να ενημερωθούν άλλα components
        const event = new Event('change');
        chapterSelect.dispatchEvent(event);
    }
}

/**
 * Ακύρωση δημιουργίας νέου στοιχείου
 * @param {string} type - Ο τύπος του στοιχείου ('subcategory' ή 'chapter')
 */
function cancelNewItem(type) {
    console.log(`🔄 Ακύρωση δημιουργίας νέου ${type}`);
    
    if (type === 'subcategory') {
        // Απόκρυψη της φόρμας
        document.getElementById('new-subcategory-form').style.display = 'none';
        
        // Επαναφορά του dropdown στην προεπιλεγμένη επιλογή
        const subcategorySelect = document.getElementById('subcategory_id');
        subcategorySelect.value = '';
        
        // Πυροδότηση του event change για να ενημερωθούν άλλα components
        const event = new Event('change');
        subcategorySelect.dispatchEvent(event);
    } else if (type === 'chapter') {
        // Απόκρυψη της φόρμας
        document.getElementById('new-chapter-form').style.display = 'none';
        
        // Επαναφορά του dropdown στην προεπιλεγμένη επιλογή
        const chapterSelect = document.getElementById('chapter_id');
        chapterSelect.value = '';
    }
}

/**
 * Αρχικοποίηση της μεταφοράς του αρχείου στο κρυφό input
 */
function initFileTransfer() {
    const fileInput = document.getElementById('csv_file');
    const hiddenFileInput = document.getElementById('csv_file_hidden');
    
    if (!hiddenFileInput) {
        console.log("❗ Δεν βρέθηκε το κρυφό input αρχείου");
        return;
    }
    
    if (fileInput && fileInput.files && fileInput.files[0]) {
        console.log("🔄 Μεταφορά αρχείου στο κρυφό input");
        
        try {
            // Δημιουργία ενός νέου DataTransfer αντικειμένου
            const dataTransfer = new DataTransfer();
            
            // Προσθήκη του επιλεγμένου αρχείου
            dataTransfer.items.add(fileInput.files[0]);
            
            // Ορισμός των αρχείων στο κρυφό input
            hiddenFileInput.files = dataTransfer.files;
            
            console.log("✅ Το αρχείο μεταφέρθηκε επιτυχώς");
        } catch (error) {
            console.error("❌ Σφάλμα κατά τη μεταφορά του αρχείου:", error);
            
            // Εναλλακτική προσέγγιση αν το DataTransfer δεν υποστηρίζεται
            // Δημιουργία ενός κρυφού πεδίου για να διατηρήσουμε το όνομα του αρχείου
            const fileNameInput = document.createElement('input');
            fileNameInput.type = 'hidden';
            fileNameInput.name = 'csv_file_name';
            fileNameInput.value = fileInput.files[0].name;
            document.querySelector('form').appendChild(fileNameInput);
        }
    }
}

/**
 * Αρχικοποίηση της επικύρωσης της φόρμας πριν την υποβολή
 */
function initFormValidation() {
    const form = document.querySelector('form');
    
    if (!form) {
        console.log("❗ Δεν βρέθηκε η φόρμα εισαγωγής");
        return;
    }
    
    form.addEventListener('submit', function(event) {
        // Έλεγχος αν χρησιμοποιούμε κατηγοριοποίηση από το CSV
        const useCSVYes = document.getElementById('use_csv_yes');
        const useCSVNo = document.getElementById('use_csv_no');
        
        if (useCSVYes && useCSVYes.checked) {
            // Αν χρησιμοποιούμε κατηγοριοποίηση από το CSV, δεν χρειάζεται έλεγχος
            console.log("🔄 Χρήση κατηγοριοποίησης από το CSV, παράλειψη ελέγχου πεδίων");
            return true;
        }
        
        // Έλεγχος αν έχουν επιλεγεί κατηγορία, υποκατηγορία και κεφάλαιο
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        const chapterSelect = document.getElementById('chapter_id');
        
        if (!categorySelect || !categorySelect.value) {
            event.preventDefault();
            alert('Παρακαλώ επιλέξτε κατηγορία.');
            return false;
        }
        
        if (!subcategorySelect || !subcategorySelect.value) {
            event.preventDefault();
            alert('Παρακαλώ επιλέξτε υποκατηγορία.');
            return false;
        }
        
        if (!chapterSelect || !chapterSelect.value) {
            event.preventDefault();
            alert('Παρακαλώ επιλέξτε κεφάλαιο.');
            return false;
        }
        
        console.log("✅ Επικύρωση φόρμας επιτυχής");
        return true;
    });
}

/**
 * Ενημέρωση των dropdown με δεδομένα από το server
 * @param {string} url - Το URL του endpoint
 * @param {Object} params - Οι παράμετροι του αιτήματος
 * @param {function} callback - Η συνάρτηση callback που θα κληθεί με τα αποτελέσματα
 */
function fetchData(url, params, callback) {
    // Μετατροπή των παραμέτρων σε query string
    const queryString = Object.keys(params)
        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
        .join('&');
    
    // Εκτέλεση του fetch
    fetch(url + (queryString ? '?' + queryString : ''), {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (callback && typeof callback === 'function') {
            callback(data);
        }
    })
    .catch(error => {
        console.error('❌ Σφάλμα κατά τη λήψη δεδομένων:', error);
    });
}

/**
 * Αποστολή δεδομένων στον server
 * @param {string} url - Το URL του endpoint
 * @param {Object} data - Τα δεδομένα που θα αποσταλούν
 * @param {function} callback - Η συνάρτηση callback που θα κληθεί με τα αποτελέσματα
 */
function postData(url, data, callback) {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (callback && typeof callback === 'function') {
            callback(data);
        }
    })
    .catch(error => {
        console.error('❌ Σφάλμα κατά την αποστολή δεδομένων:', error);
    });
}

/**
 * Δημιουργία ενός στοιχείου τύπου "Νέο στοιχείο" με AJAX
 * @param {string} type - Ο τύπος του στοιχείου ('category', 'subcategory', ή 'chapter')
 * @param {Object} data - Τα δεδομένα του νέου στοιχείου
 * @param {function} callback - Η συνάρτηση callback που θα κληθεί με τα αποτελέσματα
 */
function createNewItemAjax(type, data, callback) {
    // Δημιουργία των δεδομένων που θα αποσταλούν
    const postData = {
        action: 'create_' + type,
        data: data
    };
    
    // Αποστολή του αιτήματος
    fetch('question_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=' + postData.action + '&data=' + JSON.stringify(postData.data)
    })
    .then(response => response.json())
    .then(result => {
        if (callback && typeof callback === 'function') {
            callback(result);
        }
    })
    .catch(error => {
        console.error(`❌ Σφάλμα κατά τη δημιουργία ${type}:`, error);
    });
}

/**
 * Βοηθητική συνάρτηση για εύρεση του BASE_URL
 * @returns {string} Το BASE_URL της εφαρμογής
 */
function getBaseUrl() {
    // Έλεγχος αν υπάρχει βασικό στοιχείο base
    const baseElement = document.querySelector('base');
    if (baseElement) {
        return baseElement.href;
    }
    
    // Εναλλακτικά, εξαγωγή από τα link ή script tags
    const scriptTags = document.querySelectorAll('script[src]');
    for (let i = 0; i < scriptTags.length; i++) {
        const src = scriptTags[i].getAttribute('src');
        if (src.includes('/admin/assets/js/')) {
            return src.split('/admin/assets/js/')[0];
        }
    }
    
    // Αν δεν βρεθεί, επιστροφή του τρέχοντος window.location.origin
    return window.location.origin;
}

/**
 * Δημιουργία ειδοποίησης στην οθόνη
 * @param {string} message - Το μήνυμα που θα εμφανιστεί
 * @param {string} type - Ο τύπος της ειδοποίησης ('success', 'error', 'warning', 'info')
 * @param {number} duration - Η διάρκεια εμφάνισης σε ms (προεπιλογή: 3000ms)
 */
function showNotification(message, type = 'info', duration = 3000) {
    // Έλεγχος αν υπάρχει ήδη container για τις ειδοποιήσεις
    let notificationContainer = document.getElementById('notification-container');
    
    if (!notificationContainer) {
        notificationContainer = document.createElement('div');
        notificationContainer.id = 'notification-container';
        notificationContainer.style.position = 'fixed';
        notificationContainer.style.top = '20px';
        notificationContainer.style.right = '20px';
        notificationContainer.style.zIndex = '9999';
        document.body.appendChild(notificationContainer);
    }
    
    // Δημιουργία της ειδοποίησης
    const notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.innerHTML = message;
    
    // Στυλ της ειδοποίησης
    notification.style.backgroundColor = getColorForType(type);
    notification.style.color = '#fff';
    notification.style.padding = '12px 20px';
    notification.style.marginBottom = '10px';
    notification.style.borderRadius = '4px';
    notification.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-20px)';
    notification.style.transition = 'all 0.3s ease';
    
    // Προσθήκη της ειδοποίησης στο container
    notificationContainer.appendChild(notification);
    
    // Εμφάνιση της ειδοποίησης (με μικρή καθυστέρηση για το animation)
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    }, 10);
    
    // Αυτόματη απόκρυψη μετά από τον καθορισμένο χρόνο
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        
        // Αφαίρεση από το DOM μετά το animation
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, duration);
    
    // Βοηθητική συνάρτηση για το χρώμα της ειδοποίησης
    function getColorForType(type) {
        switch (type) {
            case 'success': return '#28a745';
            case 'error': return '#dc3545';
            case 'warning': return '#ffc107';
            case 'info': return '#17a2b8';
            default: return '#6c757d';
        }
    }
}