/**
 * DriveTest Bulk Import Scripts - Ευέλικτη έκδοση
 * JavaScript για τη μαζική εισαγωγή ερωτήσεων
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log("🔄 Bulk Import JS loaded");
    
    // Διαχείριση μεθόδου εισαγωγής
    initImportMode();
    
    // Διαχείριση αλυσιδωτών dropdowns για κατηγορίες/υποκατηγορίες/κεφάλαια
    initCategoryChain();
    
    // Διαχείριση του αρχείου CSV
    initFileUpload();
    
    // Αναζήτηση στοιχείων αφού φορτωθεί η σελίδα
    examinePageStructure();
});

/**
 * Εξέταση της δομής της σελίδας για διάγνωση προβλημάτων
 */
function examinePageStructure() {
    console.log("📋 Εξέταση δομής σελίδας για διάγνωση προβλημάτων");
    
    // Βρες όλα τα select της σελίδας
    const allSelects = document.querySelectorAll('select');
    console.log(`📋 Βρέθηκαν ${allSelects.length} select στη σελίδα`);
    
    allSelects.forEach((select, index) => {
        const id = select.id || 'Χωρίς ID';
        const name = select.name || 'Χωρίς name';
        const options = select.querySelectorAll('option').length;
        console.log(`📋 Select #${index+1}: ID="${id}", name="${name}", options=${options}`);
        
        // Αν είναι πιθανό dropdown κατηγορίας/υποκατηγορίας/κεφαλαίου
        if (id.toLowerCase().includes('category') || 
            id.toLowerCase().includes('subcategory') || 
            id.toLowerCase().includes('chapter') ||
            name.toLowerCase().includes('category') || 
            name.toLowerCase().includes('subcategory') || 
            name.toLowerCase().includes('chapter')) {
            console.log(`   📌 Πιθανό dropdown για υλοποίηση αλυσίδας`);
        }
    });
    
    // Επιπλέον έλεγχοι
    const formElement = document.querySelector('form');
    if (formElement) {
        console.log(`📋 Βρέθηκε φόρμα με action="${formElement.action}", method="${formElement.method}"`);
    } else {
        console.log(`⚠️ Δεν βρέθηκε φόρμα στη σελίδα`);
    }
}

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
    console.log("🔄 Αρχικοποίηση αλυσιδωτών dropdowns");
    
    // Ευέλικτη αναζήτηση των select στοιχείων
    const categorySelect = findSelectByIdentifiers(['category', 'κατηγορία'], ['subcategory', 'υποκατηγορία', 'chapter', 'κεφάλαιο']);
    const subcategorySelect = findSelectByIdentifiers(['subcategory', 'υποκατηγορία'], ['chapter', 'κεφάλαιο']);
    const chapterSelect = findSelectByIdentifiers(['chapter', 'κεφάλαιο'], []);
    
    console.log(`📋 Εντοπίστηκαν dropdowns: Category=${categorySelect ? 'Ναι' : 'Όχι'}, Subcategory=${subcategorySelect ? 'Ναι' : 'Όχι'}, Chapter=${chapterSelect ? 'Ναι' : 'Όχι'}`);
    
    if (categorySelect) {
        console.log(`✅ Βρέθηκε το select κατηγορίας με ID="${categorySelect.id}", name="${categorySelect.name}"`);
        
        // Εκκαθάριση και απενεργοποίηση των dropdown που εξαρτώνται
        if (subcategorySelect) {
            subcategorySelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Κατηγορία --</option>';
            subcategorySelect.disabled = true;
        }
        
        if (chapterSelect) {
            chapterSelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>';
            chapterSelect.disabled = true;
        }
        
        // Προσθήκη listener για αλλαγές στην κατηγορία
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            console.log(`🔄 Επιλέχθηκε κατηγορία με ID: ${categoryId}`);
            
            if (categoryId) {
                // Φόρτωση υποκατηγοριών για τη επιλεγμένη κατηγορία
                loadSubcategories(categoryId, subcategorySelect);
            } else {
                // Εκκαθάριση και απενεργοποίηση των εξαρτημένων dropdown
                if (subcategorySelect) {
                    subcategorySelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Κατηγορία --</option>';
                    subcategorySelect.disabled = true;
                }
                
                if (chapterSelect) {
                    chapterSelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>';
                    chapterSelect.disabled = true;
                }
            }
        });
    } else {
        console.log("⚠️ Το select κατηγορίας δε βρέθηκε");
    }
    
    // Προσθήκη listener για αλλαγές στην υποκατηγορία
    if (subcategorySelect) {
        console.log(`✅ Βρέθηκε το select υποκατηγορίας με ID="${subcategorySelect.id}", name="${subcategorySelect.name}"`);
        
        subcategorySelect.addEventListener('change', function() {
            const subcategoryId = this.value;
            console.log(`🔄 Επιλέχθηκε υποκατηγορία με ID: ${subcategoryId}`);
            
            if (subcategoryId) {
                // Φόρτωση κεφαλαίων για την επιλεγμένη υποκατηγορία
                loadChapters(subcategoryId, chapterSelect);
            } else {
                // Εκκαθάριση και απενεργοποίηση του dropdown κεφαλαίων
                if (chapterSelect) {
                    chapterSelect.innerHTML = '<option value="">-- Επιλέξτε πρώτα Υποκατηγορία --</option>';
                    chapterSelect.disabled = true;
                }
            }
        });
    } else {
        console.log("⚠️ Το select υποκατηγορίας δε βρέθηκε");
    }
    
    if (chapterSelect) {
        console.log(`✅ Βρέθηκε το select κεφαλαίου με ID="${chapterSelect.id}", name="${chapterSelect.name}"`);
    } else {
        console.log("⚠️ Το select κεφαλαίου δε βρέθηκε");
    }
    
    // Αν το categorySelect έχει ήδη επιλεγμένη τιμή
    if (categorySelect && categorySelect.value) {
        console.log("🔄 Το categorySelect έχει ήδη τιμή, πυροδοτείται η αλλαγή");
        const event = new Event('change');
        categorySelect.dispatchEvent(event);
    }
}

/**
 * Βρίσκει ένα select στοιχείο βάσει λέξεων-κλειδιών στο id ή name
 * @param {Array} includeTerms - Λέξεις που πρέπει να περιλαμβάνονται
 * @param {Array} excludeTerms - Λέξεις που δεν πρέπει να περιλαμβάνονται
 * @returns {HTMLElement|null} - Το select στοιχείο ή null
 */
function findSelectByIdentifiers(includeTerms, excludeTerms) {
    const allSelects = document.querySelectorAll('select');
    
    for (let select of allSelects) {
        const id = (select.id || '').toLowerCase();
        const name = (select.name || '').toLowerCase();
        
        // Έλεγχος αν περιλαμβάνει τις απαιτούμενες λέξεις
        const hasIncludeTerm = includeTerms.some(term => 
            id.includes(term.toLowerCase()) || name.includes(term.toLowerCase())
        );
        
        // Έλεγχος αν ΔΕΝ περιλαμβάνει τις εξαιρούμενες λέξεις
        const hasExcludeTerm = excludeTerms.some(term => 
            id.includes(term.toLowerCase()) || name.includes(term.toLowerCase())
        );
        
        if (hasIncludeTerm && !hasExcludeTerm) {
            return select;
        }
    }
    
    return null;
}

/**
 * Φόρτωση υποκατηγοριών για συγκεκριμένη κατηγορία
 * @param {string} categoryId - Το ID της επιλεγμένης κατηγορίας
 * @param {HTMLElement} subcategorySelect - Το select της υποκατηγορίας
 */
function loadSubcategories(categoryId, subcategorySelect) {
    console.log(`🔄 Φόρτωση υποκατηγοριών για κατηγορία ID: ${categoryId}`);
    
    if (!subcategorySelect) {
        console.error("❌ Το select υποκατηγορίας δε βρέθηκε");
        return;
    }
    
    // Αρχικοποίηση του dropdown
    subcategorySelect.innerHTML = '<option value="">-- Φόρτωση... --</option>';
    subcategorySelect.disabled = true;
    
    // Κάνουμε το AJAX αίτημα για να πάρουμε τις υποκατηγορίες
    fetch('question_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=list_subcategories'
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 Λήφθηκαν δεδομένα υποκατηγοριών:", data);
        
        if (data.success) {
            // Καθαρισμός του dropdown
            subcategorySelect.innerHTML = '<option value="">-- Επιλέξτε Υποκατηγορία --</option>';
            
            // Φιλτράρισμα των υποκατηγοριών για τη συγκεκριμένη κατηγορία
            const filteredSubcategories = data.subcategories.filter(subcategory => {
                // Ελέγχουμε διάφορα πιθανά ονόματα πεδίων
                const subCategoryId = subcategory.test_category_id || subcategory.category_id;
                return String(subCategoryId) === String(categoryId);
            });
            
            console.log(`📊 Βρέθηκαν ${filteredSubcategories.length} υποκατηγορίες για την κατηγορία ${categoryId}`);
            
            // Προσθήκη των υποκατηγοριών στο dropdown
            filteredSubcategories.forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.text = subcategory.name || subcategory.subcategory_name;
                option.setAttribute('data-category', categoryId);
                subcategorySelect.appendChild(option);
            });
            
            // Ενεργοποίηση του dropdown
            subcategorySelect.disabled = false;
            
            // Αν δεν βρέθηκαν υποκατηγορίες
            if (filteredSubcategories.length === 0) {
                subcategorySelect.innerHTML = '<option value="">-- Δεν βρέθηκαν υποκατηγορίες --</option>';
            }
        } else {
            console.error("❌ Σφάλμα κατά τη φόρτωση υποκατηγοριών:", data.message);
            subcategorySelect.innerHTML = '<option value="">-- Σφάλμα φόρτωσης --</option>';
        }
    })
    .catch(error => {
        console.error("❌ Σφάλμα AJAX κατά τη φόρτωση υποκατηγοριών:", error);
        subcategorySelect.innerHTML = '<option value="">-- Σφάλμα επικοινωνίας --</option>';
    });
}

/**
 * Φόρτωση κεφαλαίων για συγκεκριμένη υποκατηγορία
 * @param {string} subcategoryId - Το ID της επιλεγμένης υποκατηγορίας
 * @param {HTMLElement} chapterSelect - Το select του κεφαλαίου
 */
function loadChapters(subcategoryId, chapterSelect) {
    console.log(`🔄 Φόρτωση κεφαλαίων για υποκατηγορία ID: ${subcategoryId}`);
    
    if (!chapterSelect) {
        console.error("❌ Το select κεφαλαίου δε βρέθηκε");
        return;
    }
    
    // Αρχικοποίηση του dropdown
    chapterSelect.innerHTML = '<option value="">-- Φόρτωση... --</option>';
    chapterSelect.disabled = true;
    
    // Κάνουμε το AJAX αίτημα για να πάρουμε τα κεφάλαια
    fetch('question_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=list_chapters&subcategory_id=' + subcategoryId
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 Λήφθηκαν δεδομένα κεφαλαίων:", data);
        
        if (data.success) {
            // Καθαρισμός του dropdown
            chapterSelect.innerHTML = '<option value="">-- Επιλέξτε Κεφάλαιο --</option>';
            
            // Προσθήκη των κεφαλαίων στο dropdown
            data.chapters.forEach(chapter => {
                const option = document.createElement('option');
                option.value = chapter.id;
                option.text = chapter.name;
                option.setAttribute('data-subcategory', subcategoryId);
                chapterSelect.appendChild(option);
            });
            
            // Ενεργοποίηση του dropdown
            chapterSelect.disabled = false;
            
            // Αν δεν βρέθηκαν κεφάλαια
            if (data.chapters.length === 0) {
                chapterSelect.innerHTML = '<option value="">-- Δεν βρέθηκαν κεφάλαια --</option>';
            }
        } else {
            console.error("❌ Σφάλμα κατά τη φόρτωση κεφαλαίων:", data.message);
            chapterSelect.innerHTML = '<option value="">-- Σφάλμα φόρτωσης --</option>';
        }
    })
    .catch(error => {
        console.error("❌ Σφάλμα AJAX κατά τη φόρτωση κεφαλαίων:", error);
        chapterSelect.innerHTML = '<option value="">-- Σφάλμα επικοινωνίας --</option>';
    });
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