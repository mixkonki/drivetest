document.addEventListener("DOMContentLoaded", function () {
    console.log("🔄 [INFO] chapter_manager.js Loaded");

    loadChapters();
    loadSubcategories(); // Φόρτωση υποκατηγοριών

    // Προσθήκη listener για το κουμπί προσθήκης νέου κεφαλαίου
    const addButton = document.getElementById("add-chapter-btn");
    if (addButton) {
        addButton.addEventListener("click", function () {
            addChapterRow();
        });
    }
});

// ✅ Φόρτωση υποκατηγοριών για το dropdown
function loadSubcategories() {
    console.log("🔄 [INFO] Φόρτωση υποκατηγοριών...");

    fetch("chapter_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_subcategories"
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);

        if (data.success) {
            window.subcategories = data.subcategories;
            console.log("✅ [INFO] Υποκατηγορίες αποθηκεύτηκαν:", window.subcategories);
        } else {
            console.error("❌ [ERROR] Σφάλμα στη φόρτωση υποκατηγοριών:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα AJAX:", error));
}

// ✅ Φόρτωση κεφαλαίων
function loadChapters() {
    console.log("🔄 [INFO] Φόρτωση κεφαλαίων...");

    fetch("chapter_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let tableBody = document.getElementById("chapter-list");
            tableBody.innerHTML = "";

            data.chapters.forEach(chapter => {
                let row = document.createElement("tr");
                row.dataset.id = chapter.id;
                
                // Προετοιμασία HTML για εικονίδιο (αν υπάρχει)
                let iconHtml = '';
                if (chapter.icon) {
                    iconHtml = `<img src="${chapter.icon_url}" alt="Εικονίδιο ${chapter.name}" width="30" height="30">`;
                } else {
                    iconHtml = '-';
                }
                
                row.innerHTML = `
                    <td>${chapter.name}</td>
                    <td>${chapter.category_name} - ${chapter.subcategory_name}</td>
                    <td>${chapter.description}</td>
                    <td>${iconHtml}</td>
                    <td>
                        <a href="edit_chapter.php?id=${chapter.id}" class="btn-edit" title="Επεξεργασία"><i class="action-icon">✏️</i></a>
                        <a href="delete_chapter.php?id=${chapter.id}" class="btn-delete" title="Διαγραφή"><i class="action-icon">❌</i></a>
                    </td>
                `;

                tableBody.appendChild(row);
            });
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα φόρτωσης κεφαλαίων:", error));
}

// ✅ Προσθήκη νέου κεφαλαίου
function addChapterRow() {
    console.log("➕ [INFO] Προσθήκη νέου κεφαλαίου...");
    let tableBody = document.getElementById("chapter-list");
    let row = document.createElement("tr");

    if (!window.subcategories || window.subcategories.length === 0) {
        alert("❌ Δεν υπάρχουν διαθέσιμες υποκατηγορίες.");
        return;
    }

    let subcategoryOptions = window.subcategories.map(
        sub => `<option value="${sub.subcategory_id}">${sub.category_name} - ${sub.subcategory_name}</option>`
    ).join("");

    row.innerHTML = `
        <td><input type="text" class="chapter-name" placeholder="Όνομα κεφαλαίου"></td>
        <td>
            <select class="chapter-subcategory">
                ${subcategoryOptions}
            </select>
        </td>
        <td><textarea class="chapter-description" placeholder="Περιγραφή"></textarea></td>
        <td>
            <div class="icon-upload">
                <input type="file" class="chapter-icon-file" accept="image/*" style="display: none;">
                <button type="button" class="browse-icon-btn">📷 Εικόνα</button>
                <input type="text" class="chapter-icon-url" placeholder="URL Εικόνας" style="margin-top: 5px;">
                <div class="icon-preview" style="display: none; margin-top: 5px;">
                    <img src="" alt="Προεπισκόπηση" width="30" height="30">
                </div>
            </div>
        </td>
        <td>
            <button class="save-chapter-btn">💾 Αποθήκευση</button>
            <button class="cancel-chapter-btn">❌ Ακύρωση</button>
        </td>
    `;

    tableBody.appendChild(row);

    // Προσθήκη event listeners
    row.querySelector(".browse-icon-btn").addEventListener("click", function() {
        row.querySelector(".chapter-icon-file").click();
    });
    
    row.querySelector(".chapter-icon-file").addEventListener("change", function(e) {
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
    
    row.querySelector(".chapter-icon-url").addEventListener("input", function() {
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

    row.querySelector(".save-chapter-btn").addEventListener("click", function () {
        saveChapter(row);
    });

    row.querySelector(".cancel-chapter-btn").addEventListener("click", function () {
        row.remove();
    });
}

// ✅ Αποθήκευση νέου κεφαλαίου
function saveChapter(row) {
    let name = row.querySelector(".chapter-name").value.trim();
    let subcategory_id = row.querySelector(".chapter-subcategory").value;
    let description = row.querySelector(".chapter-description").value.trim();
    const iconUrl = row.querySelector(".chapter-icon-url").value.trim();
    const iconFile = row.querySelector(".chapter-icon-file").files[0];

    if (!name || !subcategory_id) {
        alert("❌ Συμπληρώστε όλα τα υποχρεωτικά πεδία!");
        return;
    }

    // Δημιουργία FormData για αποστολή αρχείων
    const formData = new FormData();
    formData.append("action", "save");
    formData.append("name", name);
    formData.append("subcategory_id", subcategory_id);
    formData.append("description", description);
    
    if (iconFile) {
        formData.append("icon_file", iconFile);
    } else if (iconUrl) {
        formData.append("icon", iconUrl);
    }

    console.log("💾 [INFO] Αποθήκευση νέου κεφαλαίου:", { name, subcategory_id, description });
    
    // Εμφάνιση ένδειξης φόρτωσης
    row.classList.add("loading");

    fetch("chapter_actions.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);
        row.classList.remove("loading");
        
        if (data.success) {
            alert("✅ Το κεφάλαιο αποθηκεύτηκε!");
            loadChapters(); // 🔄 Ανανεώνει τη λίστα
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

// ✅ Επεξεργασία κεφαλαίου
function editChapter(chapter) {
    console.log("✏️ [INFO] Επεξεργασία κεφαλαίου:", chapter);

    let row = document.querySelector(`tr[data-id="${chapter.id}"]`);
    
    // Προετοιμασία HTML για εικονίδιο
    let iconHtml = `
        <div class="icon-upload">
            <input type="file" class="edit-icon-file" accept="image/*" style="display: none;">
            <button type="button" class="browse-icon-btn">📷 Εικόνα</button>
            <input type="text" class="edit-icon-url" value="${chapter.icon || ''}" placeholder="URL Εικόνας" style="margin-top: 5px;">
    `;
    
    if (chapter.icon) {
        iconHtml += `
            <div class="icon-preview" style="margin-top: 5px;">
                <img src="${chapter.icon_url}" alt="Προεπισκόπηση" width="30" height="30">
            </div>
        `;
    } else {
        iconHtml += `
            <div class="icon-preview" style="display: none; margin-top: 5px;">
                <img src="" alt="Προεπισκόπηση" width="30" height="30">
            </div>
        `;
    }
    
    iconHtml += `</div>`;

    row.innerHTML = `
        <td><input type="text" class="edit-name" value="${chapter.name}"></td>
        <td>
            <select class="edit-subcategory">
                ${window.subcategories.map(sub => 
                    `<option value="${sub.subcategory_id}" ${sub.subcategory_id == chapter.subcategory_id ? 'selected' : ''}>${sub.category_name} - ${sub.subcategory_name}</option>`
                ).join("")}
            </select>
        </td>
        <td><textarea class="edit-description">${chapter.description}</textarea></td>
        <td>${iconHtml}</td>
        <td>
            <button class="save-edit-btn">💾 Αποθήκευση</button>
            <button class="cancel-edit-btn">❌ Ακύρωση</button>
        </td>
    `;

    // Προσθήκη event listeners για χειρισμό εικονιδίων
    row.querySelector(".browse-icon-btn").addEventListener("click", function() {
        row.querySelector(".edit-icon-file").click();
    });
    
    row.querySelector(".edit-icon-file").addEventListener("change", function(e) {
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
    
    row.querySelector(".edit-icon-url").addEventListener("input", function() {
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

    row.querySelector(".save-edit-btn").addEventListener("click", function () {
        saveEditedChapter(chapter.id, row);
    });

    row.querySelector(".cancel-edit-btn").addEventListener("click", function () {
        loadChapters();
    });
}

// ✅ Αποθήκευση επεξεργασμένου κεφαλαίου
function saveEditedChapter(chapterId, row) {
    let name = row.querySelector(".edit-name").value.trim();
    let subcategory_id = row.querySelector(".edit-subcategory").value;
    let description = row.querySelector(".edit-description").value.trim();
    const iconUrl = row.querySelector(".edit-icon-url").value.trim();
    const iconFile = row.querySelector(".edit-icon-file").files[0];

    if (!name || !subcategory_id) {
        alert("❌ Συμπληρώστε όλα τα υποχρεωτικά πεδία!");
        return;
    }

    // Δημιουργία FormData για αποστολή αρχείων
    const formData = new FormData();
    formData.append("action", "edit");
    formData.append("id", chapterId);
    formData.append("name", name);
    formData.append("subcategory_id", subcategory_id);
    formData.append("description", description);
    
    if (iconFile) {
        formData.append("icon_file", iconFile);
    } else if (iconUrl) {
        formData.append("icon", iconUrl);
    }

    console.log("💾 [INFO] Ενημέρωση κεφαλαίου:", { chapterId, name, subcategory_id, description });
    
    // Εμφάνιση ένδειξης φόρτωσης
    row.classList.add("loading");

    fetch("chapter_actions.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        row.classList.remove("loading");
        
        if (data.success) {
            alert("✅ Το κεφάλαιο ενημερώθηκε!");
            loadChapters(); // 🔄 Ανανεώνει τη λίστα
        } else {
            alert("❌ Σφάλμα ενημέρωσης: " + data.message);
        }
    })
    .catch(error => {
        console.error("❌ [ERROR] Σφάλμα ενημέρωσης:", error);
        row.classList.remove("loading");
        alert("❌ Σφάλμα επικοινωνίας με τον server");
    });
}

// ✅ Διαγραφή κεφαλαίου
function deleteChapter(chapterId) {
    if (!confirm("❌ Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το κεφάλαιο;")) {
        return;
    }

    console.log("🗑️ [INFO] Διαγραφή κεφαλαίου ID:", chapterId);

    fetch("chapter_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=delete&id=${encodeURIComponent(chapterId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("✅ Το κεφάλαιο διαγράφηκε!");
            loadChapters(); // 🔄 Ανανεώνει τη λίστα
        } else {
            alert("❌ Σφάλμα διαγραφής: " + data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα διαγραφής:", error));
}