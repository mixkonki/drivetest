document.addEventListener('DOMContentLoaded', function() {
    console.log("🔄 Bulk Import JS loaded");

    // Εξέταση δομής σελίδας
    examinePageStructure();

    // Διαχείριση αρχείου CSV
    const fileInput = document.getElementById('csv_file');
    if (fileInput) {
        initFileUpload();
    }

    // Έλεγχος προεπισκόπησης
    const previewModeElements = [
        document.querySelector(".preview-table-container"),
        document.getElementById("csv_file_hidden"),
        document.querySelector('input[name="use_csv_categorization"]'),
        document.getElementById('category_id')
    ];
    console.log("🔍 Preview mode elements:", previewModeElements.map(el => el !== null));
    const isPreviewMode = previewModeElements.some(element => element !== null);
    console.log("🔍 Is preview mode:", isPreviewMode);

    if (isPreviewMode) {
        console.log("✅ Κατάσταση προεπισκόπησης");
        initCategorization();
        initCategoryChain();
        initNewItemCreation();
        initFileTransfer();
        initFormValidation();
    } else {
        console.log("ℹ️ Δεν είμαστε σε κατάσταση προεπισκόπησης");
    }

    function examinePageStructure() {
        console.log("📋 Εξέταση δομής σελίδας");
        const allSelects = document.querySelectorAll('select');
        console.log(`📋 Βρέθηκαν ${allSelects.length} select`);
        allSelects.forEach((select, index) => {
            console.log(`📋 Select #${index+1}: ID="${select.id || 'Χωρίς ID'}", name="${select.name || 'Χωρίς name'}", options=${select.options.length}`);
        });
        const formElement = document.querySelector('form');
        console.log(formElement ? `📋 Βρέθηκε φόρμα: action="${formElement.action}", method="${formElement.method}"` : "⚠️ Δεν βρέθηκε φόρμα");
    }

    function initCategorization() {
        const useCSVYes = document.getElementById('use_csv_yes');
        const useCSVNo = document.getElementById('use_csv_no');
        const categoryFields = document.getElementById('category-selection-fields');
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        const chapterSelect = document.getElementById('chapter_id');

        if (!useCSVYes || !useCSVNo || !categoryFields || !categorySelect || !subcategorySelect || !chapterSelect) {
            console.log("⚠️ Δεν βρέθηκαν όλα τα στοιχεία κατηγοριοποίησης");
            return;
        }

        console.log("✅ Αρχικοποίηση επιλογών κατηγοριοποίησης");
        useCSVYes.addEventListener('change', function() {
            if (this.checked) {
                categoryFields.style.display = 'none';
                toggleRequired(false);
            }
        });
        useCSVNo.addEventListener('change', function() {
            if (this.checked) {
                categoryFields.style.display = 'block';
                toggleRequired(true);
            }
        });
        if (useCSVYes.checked) {
            categoryFields.style.display = 'none';
            toggleRequired(false);
        } else {
            categoryFields.style.display = 'block';
            toggleRequired(true);
        }

        function toggleRequired(enable) {
            categorySelect.required = enable;
            subcategorySelect.required = enable;
            chapterSelect.required = enable;
        }
    }

    function initCategoryChain() {
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        const chapterSelect = document.getElementById('chapter_id');

        if (!categorySelect || !subcategorySelect || !chapterSelect) {
            console.log("❗ Δεν βρέθηκαν όλα τα dropdowns");
            return;
        }

        console.log("✅ Αρχικοποίηση αλυσιδωτών dropdowns");
        const subcategoryOptions = Array.from(subcategorySelect.querySelectorAll('option[data-category]'));
        const chapterOptions = Array.from(chapterSelect.querySelectorAll('option[data-subcategory]'));
        console.log(`📋 Αρχικές υποκατηγορίες: ${subcategoryOptions.length}, κεφάλαια: ${chapterOptions.length}`);

        resetSubcategoryDropdown();
        resetChapterDropdown();

        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            console.log(`🔄 Επιλέχθηκε κατηγορία: ${categoryId}`);
            resetSubcategoryDropdown();
            resetChapterDropdown();
            if (categoryId) {
                console.log(`🔍 Φιλτράρισμα υποκατηγοριών για categoryId: ${categoryId}`);
                filterOptions(subcategorySelect, subcategoryOptions, 'data-category', categoryId);
                subcategorySelect.disabled = false;
            }
        });

        subcategorySelect.addEventListener('change', function() {
            const subcategoryId = this.value;
            console.log(`🔄 Επιλέχθηκε υποκατηγορία: ${subcategoryId}`);
            resetChapterDropdown();
            if (subcategoryId === "new") {
                console.log("🔄 Επιλογή δημιουργίας νέας υποκατηγορίας");
                showNewItemForm('subcategory');
            } else if (subcategoryId) {
                console.log(`🔍 Φιλτράρισμα κεφαλαίων για subcategoryId: ${subcategoryId}`);
                filterOptions(chapterSelect, chapterOptions, 'data-subcategory', subcategoryId);
                chapterSelect.disabled = false;
            }
        });

        chapterSelect.addEventListener('change', function() {
            const chapterId = this.value;
            console.log(`🔄 Επιλέχθηκε κεφάλαιο: ${chapterId}`);
            if (chapterId === "new") {
                console.log("🔄 Επιλογή δημιουργίας νέου κεφαλαίου");
                showNewItemForm('chapter');
            }
        });

        if (categorySelect.value) {
            console.log(`📋 Προεπιλεγμένη κατηγορία: ${categorySelect.value}`);
            categorySelect.dispatchEvent(new Event('change'));
            if (subcategorySelect.value) {
                console.log(`📋 Προεπιλεγμένη υποκατηγορία: ${subcategorySelect.value}`);
                subcategorySelect.dispatchEvent(new Event('change'));
            }
        }

        function resetSubcategoryDropdown() {
            subcategorySelect.innerHTML = '<option value="">-- Επιλέξτε Υποκατηγορία --</option>';
            subcategorySelect.disabled = true;
            console.log("🔄 Επαναφορά dropdown υποκατηγοριών");
        }

        function resetChapterDropdown() {
            chapterSelect.innerHTML = '<option value="">-- Επιλέξτε Κεφάλαιο --</option>';
            chapterSelect.disabled = true;
            console.log("🔄 Επαναφορά dropdown κεφαλαίων");
        }

        function filterOptions(select, options, dataAttr, value) {
            select.innerHTML = '<option value="">-- Επιλέξτε --</option>';
            console.log(`🔍 Συνολικές επιλογές για ${select.id}: ${options.length}`);
            let foundOptions = 0;
            options.forEach(opt => {
                if (opt.getAttribute(dataAttr) === value) {
                    const clone = opt.cloneNode(true);
                    clone.style.display = 'block';
                    select.appendChild(clone);
                    foundOptions++;
                }
            });
            console.log(`✅ Βρέθηκαν ${foundOptions} επιλογές για ${dataAttr}=${value}`);
            if (foundOptions === 0) console.log("⚠️ Καμία επιλογή δεν βρέθηκε");
            const newOption = document.createElement('option');
            newOption.value = "new";
            newOption.textContent = "+ Δημιουργία Νέου";
            select.appendChild(newOption);
        }
    }

    function initFileUpload() {
        const fileInput = document.getElementById('csv_file');
        const previewForm = document.getElementById('preview-form');
        if (!fileInput || !previewForm) return;

        console.log("✅ Αρχικοποίηση διαχείρισης αρχείου CSV");
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                if (this.files[0].size > 10 * 1024 * 1024) {
                    alert('Το αρχείο είναι πολύ μεγάλο (max 10MB).');
                    this.value = '';
                    return;
                }
                if (!this.files[0].name.toLowerCase().endsWith('.csv')) {
                    alert('Επιλέξτε έγκυρο αρχείο CSV.');
                    this.value = '';
                    return;
                }
                console.log("📤 Υποβολή φόρμας προεπισκόπησης με αρχείο: " + this.files[0].name);
                previewForm.submit();
            }
        });
    }

    function initFileTransfer() {
        const fileInput = document.getElementById('csv_file');
        const hiddenFileInput = document.getElementById('csv_file_hidden');
        if (!hiddenFileInput || !fileInput || !fileInput.files[0]) {
            console.log("⚠️ Δεν βρέθηκαν στοιχεία για μεταφορά αρχείου");
            return;
        }
        console.log("🔄 Μεταφορά αρχείου από " + fileInput.files[0].name);
        try {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(fileInput.files[0]);
            hiddenFileInput.files = dataTransfer.files;
            console.log("✅ Μεταφορά επιτυχής - Αρχείο: " + (hiddenFileInput.files[0] ? hiddenFileInput.files[0].name : "κανένα"));
        } catch (error) {
            console.error("❌ Σφάλμα μεταφοράς:", error);
            const altFileInput = document.createElement('input');
            altFileInput.type = 'hidden';
            altFileInput.name = 'temp_csv_path';
            altFileInput.value = fileInput.getAttribute('data-temp-path') || '';
            document.querySelector('form').appendChild(altFileInput);
            console.log("✅ Χρήση εναλλακτικής μεθόδου με προσωρινό αρχείο");
        }
    }

    function initNewItemCreation() {
        const categoryFields = document.getElementById('category-selection-fields');
        if (!categoryFields) return;

        console.log("✅ Αρχικοποίηση δημιουργίας νέων στοιχείων");
        ['subcategory', 'chapter'].forEach(type => {
            if (!document.getElementById(`new-${type}-form`)) {
                const form = document.createElement('div');
                form.id = `new-${type}-form`;
                form.style.display = 'none';
                form.innerHTML = `
                    <label>Όνομα Νέου ${type === 'subcategory' ? 'Υποκατηγορίας' : 'Κεφαλαίου'}:</label>
                    <input type="text" id="new_${type}_name" placeholder="Πληκτρολογήστε όνομα">
                    <button type="button" onclick="saveNewItem('${type}')">Αποθήκευση</button>
                    <button type="button" onclick="cancelNewItem('${type}')">Ακύρωση</button>
                `;
                categoryFields.appendChild(form);
            }
        });
    }

    function initFormValidation() {
        const form = document.querySelector('form');
        if (!form) return;

        console.log("✅ Αρχικοποίηση επικύρωσης φόρμας");
        form.addEventListener('submit', function(event) {
            if (document.getElementById('use_csv_yes') && document.getElementById('use_csv_yes').checked) return;

            const selects = ['category_id', 'subcategory_id', 'chapter_id'].map(id => document.getElementById(id));
            for (let select of selects) {
                if (select && !select.value) {
                    event.preventDefault();
                    alert(`Παρακαλώ επιλέξτε ${select.id === 'category_id' ? 'κατηγορία' : select.id === 'subcategory_id' ? 'υποκατηγορία' : 'κεφάλαιο'}.`);
                    return;
                }
            }
            console.log("📥 Υποβολή φόρμας εισαγωγής");
        });
    }

    window.showNewItemForm = function(type) {
        const form = document.getElementById(`new-${type}-form`);
        if (form) {
            form.style.display = 'block';
            document.getElementById(`new_${type}_name`).focus();
        }
    };

    window.saveNewItem = function(type) {
        const nameInput = document.getElementById(`new_${type}_name`);
        const name = nameInput.value.trim();
        if (!name) {
            alert(`Εισάγετε όνομα για το νέο ${type === 'subcategory' ? 'υποκατηγορία' : 'κεφάλαιο'}.`);
            return;
        }
        const parentSelect = document.getElementById(type === 'subcategory' ? 'category_id' : 'subcategory_id');
        const select = document.getElementById(`${type}_id`);
        if (!parentSelect.value) {
            alert(`Επιλέξτε πρώτα ${type === 'subcategory' ? 'κατηγορία' : 'υποκατηγορία'}.`);
            return;
        }
        const tempId = 'new_' + Date.now();
        const option = document.createElement('option');
        option.value = tempId;
        option.textContent = name;
        option.setAttribute(`data-${type === 'subcategory' ? 'category' : 'subcategory'}`, parentSelect.value);
        select.insertBefore(option, select.querySelector('option[value="new"]'));
        select.value = tempId;
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = `new_${type}_names[${tempId}]`;
        hiddenInput.value = name;
        document.querySelector('form').appendChild(hiddenInput);
        document.getElementById(`new-${type}-form`).style.display = 'none';
        select.dispatchEvent(new Event('change'));
    };

    window.cancelNewItem = function(type) {
        const form = document.getElementById(`new-${type}-form`);
        form.style.display = 'none';
        document.getElementById(`${type}_id`).value = '';
        document.getElementById(`${type}_id`).dispatchEvent(new Event('change'));
    };
});