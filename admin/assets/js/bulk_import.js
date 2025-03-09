/**
 * DriveTest Bulk Import Scripts
 * JavaScript για τη μαζική εισαγωγή ερωτήσεων
 */

document.addEventListener('DOMContentLoaded', function() {
    // Διαχείριση μεθόδου εισαγωγής
    initImportMode();
    
    // Διαχείριση αλυσιδωτών dropdowns για κατηγορίες/υποκατηγορίες/κεφάλαια
    initCategoryChain();
    
    // Διαχείριση του αρχείου CSV
    initFileUpload();
});

/**
 * Αρχικοποίηση του τρόπου εισαγωγής
 */
function initImportMode() {
    const importModeSelect = document.getElementById('import_mode');
    const manualMappingSection = document.getElementById('manual-mapping');
    
    if (importModeSelect && manualMappingSection) {
        importModeSelect.addEventListener('change', function() {
            const selectedMode = this.value;
            
            // Εμφάνιση/απόκρυψη ανάλογων επιλογών
            if (selectedMode === 'manual') {
                manualMappingSection.style.display = 'block';
            } else if (selectedMode === 'auto') {
                manualMappingSection.style.display = 'none';
            } else if (selectedMode === 'predefined') {
                manualMappingSection.style.display = 'none';
            }
        });
        
        // Αρχικοποίηση με την τρέχουσα τιμή
        const event = new Event('change');
        importModeSelect.dispatchEvent(event);
    }
}

/**
 * Αρχικοποίηση αλυσιδωτών dropdowns για κατηγορίες/υποκατηγορίες/κεφάλαια
 */
function initCategoryChain() {
    const defaultCategorySelect = document.getElementById('default_category_id');
    const defaultSubcategorySelect = document.getElementById('default_subcategory_id');
    const defaultChapterSelect = document.getElementById('default_chapter_id');
    
    // Διατήρηση επιλεγμένων τιμών
    let selectedCategory = defaultCategorySelect ? defaultCategorySelect.value : '';
    let selectedSubcategory = defaultSubcategorySelect ? defaultSubcategorySelect.value : '';
    
    // Συγκεντρώνουμε τις υποκατηγορίες ανά κατηγορία
    const subcategoriesByCategory = {};
    if (defaultSubcategorySelect) {
        const subcategoryOptions = defaultSubcategorySelect.querySelectorAll('option');
        subcategoryOptions.forEach(option => {
            const categoryId = option.getAttribute('data-category');
            if (categoryId) {
                if (!subcategoriesByCategory[categoryId]) {
                    subcategoriesByCategory[categoryId] = [];
                }
                subcategoriesByCategory[categoryId].push({
                    value: option.value,
                    text: option.innerText
                });
            }
        });
    }
    
    // Συγκεντρώνουμε τα κεφάλαια ανά υποκατηγορία
    const chaptersBySubcategory = {};
    if (defaultChapterSelect) {
        const chapterOptions = defaultChapterSelect.querySelectorAll('option');
        chapterOptions.forEach(option => {
            const subcategoryId = option.getAttribute('data-subcategory');
            if (subcategoryId) {
                if (!chaptersBySubcategory[subcategoryId]) {
                    chaptersBySubcategory[subcategoryId] = [];
                }
                chaptersBySubcategory[subcategoryId].push({
                    value: option.value,
                    text: option.innerText
                });
            }
        });
    }
    
    // Event listener για επιλογή κατηγορίας
    if (defaultCategorySelect) {
        defaultCategorySelect.addEventListener('change', function() {
            selectedCategory = this.value;
            updateSubcategories(selectedCategory);
        });
    }
    
    // Event listener για επιλογή υποκατηγορίας
    if (defaultSubcategorySelect) {
        defaultSubcategorySelect.addEventListener('change', function() {
            selectedSubcategory = this.value;
            updateChapters(selectedSubcategory);
        });
    }
    
    /**
     * Ενημέρωση του dropdown υποκατηγοριών βάσει της επιλεγμένης κατηγορίας
     */
    function updateSubcategories(categoryId) {
        if (!defaultSubcategorySelect) return;
        
        // Καθαρισμός των επιλογών
        defaultSubcategorySelect.innerHTML = '';
        
        // Προσθήκη της προεπιλεγμένης επιλογής
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.text = '-- Καμία --';
        defaultSubcategorySelect.appendChild(defaultOption);
        
        // Προσθήκη των υποκατηγοριών της επιλεγμένης κατηγορίας
        if (categoryId && subcategoriesByCategory[categoryId]) {
            subcategoriesByCategory[categoryId].forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.value;
                option.text = subcategory.text;
                option.setAttribute('data-category', categoryId);
                defaultSubcategorySelect.appendChild(option);
            });
            
            // Ενεργοποίηση του dropdown
            defaultSubcategorySelect.disabled = false;
        } else {
            // Απενεργοποίηση αν δεν υπάρχουν υποκατηγορίες
            defaultSubcategorySelect.disabled = true;
        }
        
        // Ενημέρωση κεφαλαίων (θα γίνει καθαρισμός)
        updateChapters('');
    }
    
    /**
     * Ενημέρωση του dropdown κεφαλαίων βάσει της επιλεγμένης υποκατηγορίας
     */
    function updateChapters(subcategoryId) {
        if (!defaultChapterSelect) return;
        
        // Καθαρισμός των επιλογών
        defaultChapterSelect.innerHTML = '';
        
        // Προσθήκη της προεπιλεγμένης επιλογής
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.text = '-- Κανένα --';
        defaultChapterSelect.appendChild(defaultOption);
        
        // Προσθήκη των κεφαλαίων της επιλεγμένης υποκατηγορίας
        if (subcategoryId && chaptersBySubcategory[subcategoryId]) {
            chaptersBySubcategory[subcategoryId].forEach(chapter => {
                const option = document.createElement('option');
                option.value = chapter.value;
                option.text = chapter.text;
                option.setAttribute('data-subcategory', subcategoryId);
                defaultChapterSelect.appendChild(option);
            });
            
            // Ενεργοποίηση του dropdown
            defaultChapterSelect.disabled = false;
        } else {
            // Απενεργοποίηση αν δεν υπάρχουν κεφάλαια
            defaultChapterSelect.disabled = true;
        }
    }
    
    // Αρχικοποίηση των dropdowns
    if (defaultCategorySelect && defaultCategorySelect.value) {
        updateSubcategories(defaultCategorySelect.value);
        
        if (defaultSubcategorySelect && defaultSubcategorySelect.value) {
            updateChapters(defaultSubcategorySelect.value);
        }
    }
}

/**
 * Αρχικοποίηση της διαχείρισης του αρχείου CSV
 */
function initFileUpload() {
    const fileInput = document.getElementById('csv_file');
    const previewForm = document.getElementById('preview-form');
    const hiddenFileInput = document.getElementById('csv_file_hidden');
    
    // Αυτόματη υποβολή της φόρμας προεπισκόπησης όταν επιλεγεί αρχείο
    if (fileInput && previewForm) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // Έλεγχος μεγέθους αρχείου
                const maxFileSize = 10 * 1024 * 1024; // 10MB
                if (this.files[0].size > maxFileSize) {
                    alert('Το αρχείο είναι πολύ μεγάλο. Το μέγιστο επιτρεπόμενο μέγεθος είναι 10MB.');
                    this.value = '';
                    return;
                }
                
                // Έλεγχος τύπου αρχείου
                const fileType = this.files[0].type;
                const isCSV = fileType === 'text/csv' || 
                             fileType === 'application/vnd.ms-excel' || 
                             fileType === 'application/csv' ||
                             this.files[0].name.toLowerCase().endsWith('.csv');
                             
                if (!isCSV) {
                    alert('Παρακαλώ επιλέξτε έγκυρο αρχείο CSV.');
                    this.value = '';
                    return;
                }
                
                previewForm.submit();
            }
        });
    }
    
    // Παρακολούθηση των αλλαγών στις αντιστοιχίσεις στηλών
    const columnSelects = document.querySelectorAll('select[name^="map_"]');
    columnSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Αποφυγή διπλών αντιστοιχίσεων
            const selectedValue = this.value;
            if (selectedValue) {
                columnSelects.forEach(otherSelect => {
                    if (otherSelect !== this && otherSelect.value === selectedValue) {
                        // Αν βρούμε άλλο select με την ίδια τιμή, το καθαρίζουμε
                        otherSelect.value = '';
                    }
                });
            }
        });
    });
    
    // Μεταφορά του επιλεγμένου αρχείου στο κρυφό input
    if (hiddenFileInput && fileInput && fileInput.files.length > 0) {
        // Δημιουργία ενός νέου DataTransfer αντικειμένου
        const dataTransfer = new DataTransfer();
        // Προσθήκη του επιλεγμένου αρχείου
        dataTransfer.items.add(fileInput.files[0]);
        // Ορισμός των αρχείων στο κρυφό input
        hiddenFileInput.files = dataTransfer.files;
    }
}