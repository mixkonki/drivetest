document.addEventListener("DOMContentLoaded", function () {
    console.log("🔄 [INFO] subcategory_manager.js Loaded");

    loadCategories().then(() => loadSubcategories());

    const addBtn = document.getElementById("add-subcategory-btn");
    const form = document.getElementById("subcategory-form");
    const formData = document.getElementById("subcategory-form-data");
    const formTitle = document.getElementById("form-title");
    const cancelBtn = document.getElementById("cancel-subcategory");

    // Προσθήκη νέας υποκατηγορίας
    addBtn.addEventListener("click", () => {
        formTitle.textContent = "Προσθήκη Υποκατηγορίας";
        document.getElementById("subcategory-id").value = "";
        document.getElementById("subcategory-name").value = "";
        document.getElementById("subcategory-description").value = "";
        document.getElementById("subcategory-icon").value = "";
        form.style.display = "block";
    });

    // Αποθήκευση/Ενημέρωση υποκατηγορίας
    formData.addEventListener("submit", function(e) {
        e.preventDefault();
        const id = document.getElementById("subcategory-id").value;
        const action = id ? "edit" : "save";
        const data = new FormData(this);
        data.append("action", action);

        fetch("subcategory_actions.php", {
            method: "POST",
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                form.style.display = "none";
                loadSubcategories();
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error("❌ Σφάλμα επικοινωνίας:", error));
    });

    // Ακύρωση
    cancelBtn.addEventListener("click", () => {
        form.style.display = "none";
    });

    // Επεξεργασία
    window.editSubcategory = function(subcategory) {
        formTitle.textContent = "Επεξεργασία Υποκατηγορίας";
        document.getElementById("subcategory-id").value = subcategory.id;
        document.getElementById("subcategory-name").value = subcategory.name;
        document.getElementById("subcategory-description").value = subcategory.description || "";
        document.getElementById("subcategory-icon").value = subcategory.icon || "";
        document.getElementById("subcategory-category").value = subcategory.test_category_id;
        form.style.display = "block";
    };

    // Διαγραφή
    window.deleteSubcategory = function(id) {
        if (confirm("❌ Είστε σίγουροι ότι θέλετε να διαγράψετε αυτήν την υποκατηγορία;")) {
            fetch("subcategory_actions.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `action=delete&id=${encodeURIComponent(id)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadSubcategories();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error("❌ Σφάλμα διαγραφής:", error));
        }
    };
});

// Φόρτωση κατηγοριών
function loadCategories() {
    console.log("🔄 [INFO] Φόρτωση κατηγοριών...");
    return fetch("subcategory_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_categories"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.categories.length > 0) {
            window.categories = data.categories;
            console.log("✅ [SUCCESS] Κατηγορίες φορτώθηκαν:", window.categories);
        } else {
            console.warn("⚠️ [WARNING] Δεν υπάρχουν κατηγορίες ή σφάλμα:", data.message);
            window.categories = [];
        }
    })
    .catch(error => console.error("❌ [ERROR] Πρόβλημα στη φόρτωση κατηγοριών:", error));
}

// Φόρτωση υποκατηγοριών
function loadSubcategories() {
    console.log("🔄 [INFO] Φόρτωση υποκατηγοριών...");
    fetch("subcategory_actions.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list"
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let tableBody = document.getElementById("subcategory-list");
            tableBody.innerHTML = "";

            data.subcategories.forEach(subcategory => {
                let row = document.createElement("tr");
                row.dataset.id = subcategory.id;
                row.innerHTML = `
                    <td>${subcategory.name}</td>
                    <td>${subcategory.category_name}</td>
                    <td>${subcategory.description || '-'}</td>
                    <td>${subcategory.icon || '-'}</td>
                    <td>
                        <button class="btn-edit" onclick="editSubcategory(${JSON.stringify(subcategory)})">✏️</button>
                        <button class="btn-delete" onclick="deleteSubcategory(${subcategory.id})">❌</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            console.error("❌ [ERROR] Πρόβλημα στη φόρτωση υποκατηγοριών:", data.message);
        }
    })
    .catch(error => console.error("❌ [ERROR] Πρόβλημα στη φόρτωση υποκατηγοριών:", error));
}