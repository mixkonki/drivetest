document.addEventListener("DOMContentLoaded", function () {
    console.log("🔄 [INFO] subcategory_manager.js Loaded");

    loadSubcategories();
    loadCategories();

    // Προσθήκη listener για το κουμπί προσθήκης νέας υποκατηγορίας (αν υπάρχει)
    const addButton = document.getElementById("add-subcategory-btn");
    if (addButton) {
        addButton.addEventListener("click", function () {
            addSubcategoryRow();
        });
    }
});

// Φόρτωση όλων των υποκατηγοριών
function loadSubcategories() {
    console.log("🔄 [INFO] Φόρτωση υποκατηγοριών...");

    fetch("subcategory_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list"
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            let tableBody = document.getElementById("subcategory-list");
            if (!tableBody) {
                console.error("❌ [ERROR] Element with ID 'subcategory-list' not found");
                return;
            }
            
            tableBody.innerHTML = "";

            data.subcategories.forEach(subcategory => {
                let row = document.createElement("tr");
                row.dataset.id = subcategory.id;
                
                // Προετοιμασία URL εικονιδίου
                let iconHtml = '';
                if (subcategory.icon) {
                    iconHtml = `<img src="${subcategory.icon_url}" alt="Εικονίδιο ${subcategory.name}" width="30" height="30">`;
                } else {
                    iconHtml = '-';
                }
                
                row.innerHTML = `
                    <td>${subcategory.name}</td>
                    <td>${subcategory.category_name}</td>
                    <td>${subcategory.description || '-'}</td>
                    <td>${iconHtml}</td>
                    <td>
                        <a href="edit_subcategory.php?id=${subcategory.id}" class="btn-edit" title="Επεξεργασία"><i class="action-icon">✏️</i></a>
                        <a href="delete_subcategory.php?id=${subcategory.id}" class="btn-delete" title="Διαγραφή"><i class="action-icon">❌</i></a>
                    </td>
                `;

                tableBody.appendChild(row);
            });
            
            console.log("✅ [INFO] Υποκατηγορίες φορτώθηκαν επιτυχώς");
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση υποκατηγοριών:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// Φόρτωση κατηγοριών για το dropdown
function loadCategories() {
    console.log("🔄 [INFO] Φόρτωση κατηγοριών...");

    fetch("subcategory_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_categories"
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Categories Response:", data);

        if (data.success) {
            window.categories = data.categories;
            console.log("✅ [INFO] Κατηγορίες αποθηκεύτηκαν:", window.categories);
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση κατηγοριών:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// Προσθήκη νέας υποκατηγορίας (για τη σελίδα manage_subcategories.php)
function addSubcategoryRow() {
    console.log("➕ [INFO] Προσθήκη νέας υποκατηγορίας...");
    
    if (!window.categories || window.categories.length === 0) {
        alert("❌ Δεν υπάρχουν διαθέσιμες κατηγορίες.");
        return;
    }
    
    let tableBody = document.getElementById("subcategory-list");
    if (!tableBody) {
        console.error("❌ [ERROR] Element with ID 'subcategory-list' not found");
        return;
    }
    
    let row = document.createElement("tr");
    
    let categoryOptions = window.categories.map(
        cat => `<option value="${cat.id}">${cat.name}</option>`
    ).join("");
    
    row.innerHTML = `
        <td><input type="text" class="subcategory-name form-control" placeholder="Όνομα υποκατηγορίας"></td>
        <td>
            <select class="subcategory-category form-control">
                <option value="">-- Επιλέξτε Κατηγορία --</option>
                ${categoryOptions}
            </select>
        </td>
        <td><textarea class="subcategory-description form-control" placeholder="Περιγραφή"></textarea></td>
        <td>
            <div class="icon-upload">
                <input type="file" class="subcategory-icon-file" accept="image/*" style="display: none;">
                <button type="button" class="browse-icon-btn btn-sm">📷 Εικόνα</button>
                <input type="text" class="subcategory-icon form-control" placeholder="URL Εικόνας" style="margin-top: 5px;">
                <div class="icon-preview" style="display: none; margin-top: 5px;">
                    <img src="" alt="Προεπισκόπηση" width="30" height="30">
                </div>
            </div>
        </td>
        <td>
            <button class="save-subcategory-btn btn-primary btn-sm">💾 Αποθήκευση</button>
            <button class="cancel-subcategory-btn btn-secondary btn-sm">❌ Ακύρωση</button>
        </td>
    `;
    
    tableBody.prepend(row);
    
    // Event listeners
    row.querySelector(".browse-icon-btn").addEventListener("click", function() {
        row.querySelector(".subcategory-icon-file").click();
    });
    
    row.querySelector(".subcategory-icon-file").addEventListener("change", function(e) {
        const file = this.files[0];
        if (file) {
            // Έλεγχος αν είναι εικόνα
            if (!file.type.match('image.*')) {
                alert("Παρακαλώ επιλέξτε αρχείο εικόνας");
                return;
            }
            
            // Προεπισκόπηση
            const preview = row.querySelector(".icon-preview");
            const img = preview.querySelector("img");
            
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                preview.style.display = "block";
            };
            reader.readAsDataURL(file);
        }
    });
    
    row.querySelector(".subcategory-icon").addEventListener("input", function() {
        const url = this.value.trim();
        if (url) {
            const preview = row.querySelector(".icon-preview");
            const img = preview.querySelector("img");
            img.src = url;
            preview.style.display = "block";
        } else {
            row.querySelector(".icon-preview").style.display = "none";
        }
    });
    
    row.querySelector(".save-subcategory-btn").addEventListener("click", function() {
        saveSubcategory(row);
    });
    
    row.querySelector(".cancel-subcategory-btn").addEventListener("click", function() {
        row.remove();
    });
}

// Αποθήκευση νέας υποκατηγορίας
function saveSubcategory(row) {
    const name = row.querySelector(".subcategory-name").value.trim();
    const category_id = row.querySelector(".subcategory-category").value;
    const description = row.querySelector(".subcategory-description").value.trim();
    const icon = row.querySelector(".subcategory-icon").value.trim();
    const iconFile = row.querySelector(".subcategory-icon-file").files[0];
    
    if (!name || !category_id) {
        alert("❌ Συμπληρώστε τα υποχρεωτικά πεδία (Όνομα, Κατηγορία)!");
        return;
    }
    
    // Δημιουργία FormData για αποστολή αρχείων
    const formData = new FormData();
    formData.append("action", "save");
    formData.append("name", name);
    formData.append("category_id", category_id);
    formData.append("description", description);
    
    if (iconFile) {
        formData.append("icon_file", iconFile);
    } else if (icon) {
        formData.append("icon", icon);
    }
    
    console.log("💾 [INFO] Αποθήκευση νέας υποκατηγορίας:", { name, category_id, description });
    
    // Εμφάνιση ένδειξης φόρτωσης
    row.classList.add("loading");
    
    fetch("subcategory_actions.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);
        row.classList.remove("loading");
        
        if (data.success) {
            alert("✅ Η υποκατηγορία αποθηκεύτηκε!");
            loadSubcategories(); // Ανανεώνει τη λίστα
        } else {
            alert("❌ Σφάλμα αποθήκευσης: " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] Σφάλμα αποθήκευσης:", error);
        row.classList.remove("loading");
        alert("❌ Σφάλμα επικοινωνίας με τον server");
    });
}

// Επεξεργασία υποκατηγορίας (χρησιμοποιείται στο manage_subcategories.php)
function editSubcategory(subcategory) {
    // Ελέγχουμε αν ήδη υπάρχει γραμμή επεξεργασίας για αυτήν την υποκατηγορία
    if (document.querySelector(`tr[data-edit-id="${subcategory.id}"]`)) {
        console.log("ℹ️ [INFO] Η υποκατηγορία είναι ήδη σε επεξεργασία");
        return;
    }
    
    const row = document.querySelector(`tr[data-id="${subcategory.id}"]`);
    if (!row) {
        console.error("❌ [ERROR] Row not found for subcategory ID:", subcategory.id);
        return;
    }
    
    const categoryOptions = window.categories.map(cat => 
        `<option value="${cat.id}" ${cat.id == subcategory.test_category_id ? 'selected' : ''}>${cat.name}</option>`
    ).join("");
    
    // Αποθηκεύουμε το αρχικό περιεχόμενο για πιθανή αναίρεση
    row.dataset.originalContent = row.innerHTML;
    row.dataset.editId = subcategory.id;
    
    let iconHtml = `
        <div class="icon-upload">
            <input type="file" class="subcategory-icon-file" accept="image/*" style="display: none;">
            <button type="button" class="browse-icon-btn btn-sm">📷 Εικόνα</button>
            <input type="text" class="subcategory-icon form-control" value="${subcategory.icon || ''}" placeholder="URL Εικόνας" style="margin-top: 5px;">
    `;
    
    if (subcategory.icon) {
        iconHtml += `
            <div class="icon-preview" style="margin-top: 5px;">
                <img src="${subcategory.icon_url}" alt="Προεπισκόπηση" width="30" height="30">
            </div>
        `;}}