document.addEventListener("DOMContentLoaded", function () {
    console.log("🔄 [INFO] chapter_manager.js Loaded");

    loadChapters();
    loadSubcategories(); // 🔹 Νέο: Φόρτωση υποκατηγοριών

    document.getElementById("add-chapter-btn").addEventListener("click", function () {
        addChapterRow();
    });
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
                row.innerHTML = `
                    <td>${chapter.name}</td>
                    <td>${chapter.category_name} - ${chapter.subcategory_name}</td>
                    <td>${chapter.description}</td>
                    <td>
                        <button class="edit-chapter-btn" data-id="${chapter.id}">✏️ Επεξεργασία</button>
                        <button class="delete-chapter-btn" data-id="${chapter.id}">❌ Διαγραφή</button>
                    </td>
                `;

                tableBody.appendChild(row);

                // ✅ Προσθήκη listeners για Επεξεργασία και Διαγραφή
                row.querySelector(".edit-chapter-btn").addEventListener("click", function () {
                    editChapter(chapter);
                });

                row.querySelector(".delete-chapter-btn").addEventListener("click", function () {
                    deleteChapter(chapter.id);
                });
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
            <button class="save-chapter-btn">💾 Αποθήκευση</button>
            <button class="cancel-chapter-btn">❌ Ακύρωση</button>
        </td>
    `;

    tableBody.appendChild(row);

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

    if (!name || !subcategory_id) {
        alert("❌ Συμπληρώστε όλα τα πεδία!");
        return;
    }

    console.log("💾 [INFO] Αποθήκευση νέου κεφαλαίου:", { name, subcategory_id, description });

    fetch("chapter_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=save&name=${encodeURIComponent(name)}&subcategory_id=${encodeURIComponent(subcategory_id)}&description=${encodeURIComponent(description)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log("📥 [DEBUG] Response:", data);
        
        if (data.success) {
            alert("✅ Το κεφάλαιο αποθηκεύτηκε!");
            loadChapters(); // 🔄 Ανανεώνει τη λίστα
        } else {
            alert("❌ Σφάλμα αποθήκευσης: " + data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα αποθήκευσης:", error));
}

// ✅ Επεξεργασία κεφαλαίου
function editChapter(chapter) {
    console.log("✏️ [INFO] Επεξεργασία κεφαλαίου:", chapter);

    let row = document.querySelector(`tr[data-id="${chapter.id}"]`);

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
        <td>
            <button class="save-edit-btn">💾 Αποθήκευση</button>
            <button class="cancel-edit-btn">❌ Ακύρωση</button>
        </td>
    `;

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

    if (!name || !subcategory_id) {
        alert("❌ Συμπληρώστε όλα τα πεδία!");
        return;
    }

    console.log("💾 [INFO] Ενημέρωση κεφαλαίου:", { chapterId, name, subcategory_id, description });

    fetch("chapter_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=edit&id=${encodeURIComponent(chapterId)}&name=${encodeURIComponent(name)}&subcategory_id=${encodeURIComponent(subcategory_id)}&description=${encodeURIComponent(description)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("✅ Το κεφάλαιο ενημερώθηκε!");
            loadChapters(); // 🔄 Ανανεώνει τη λίστα
        } else {
            alert("❌ Σφάλμα ενημέρωσης: " + data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Σφάλμα ενημέρωσης:", error));
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
