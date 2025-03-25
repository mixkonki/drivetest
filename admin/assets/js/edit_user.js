console.log('Edit User JS loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event fired, initializing scripts');
    initMap();
    updateFields();

    const editBtn = document.getElementById('edit-btn');
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            console.log('Edit button clicked');
            const form = document.getElementById('user-edit-form');
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                console.log('Making input editable:', input.id || input.name || '<no-id>');
                if (input.type !== 'file') {
                    input.removeAttribute('readonly');
                    input.removeAttribute('disabled');
                } else {
                    input.disabled = false;
                }
            });
            const avatarUploadBtn = document.querySelector('.avatar-upload-btn');
            avatarUploadBtn.style.pointerEvents = 'auto';
            avatarUploadBtn.style.opacity = '1';
            this.style.display = 'none';
            const saveBtn = document.querySelector('.save-btn');
            if (saveBtn) {
                saveBtn.style.display = 'inline-block';
            } else {
                console.error('Save button not found');
            }
        });
    }

    $('#user-edit-form').on('submit', function(e) {
        console.log('Form submission initiated for user ID:', drivetestConfig.userId);
        e.preventDefault();
        const formData = new FormData(this);
        // Βεβαιωνόμαστε ότι το action περνάει στο URL
        $.ajax({
            url: drivetestConfig.baseUrl + '/admin/api/users.php?action=update&id=' + drivetestConfig.userId,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                console.log('AJAX request starting');
            },
            success: function(response) {
                console.log('Raw response:', response);
                let data;
                try {
                    data = typeof response === 'string' ? JSON.parse(response) : response;
                } catch (e) {
                    console.error('Failed to parse response:', e);
                    alert('Σφάλμα: Η απόκριση του server δεν είναι έγκυρη.');
                    return;
                }
                if (data.success) {
                    alert('Ο χρήστης ενημερώθηκε επιτυχώς.');
                    window.location.href = drivetestConfig.baseUrl + '/admin/edit_user.php?id=' + drivetestConfig.userId;
                } else {
                    console.error('Update failed:', data.message);
                    alert('Σφάλμα: ' + data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', { status: status, error: error, response: xhr.responseText });
                alert('Σφάλμα κατά την ενημέρωση χρήστη. Δες την κονσόλα για λεπτομέρειες.');
            }
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτόν τον χρήστη;')) {
                const id = this.dataset.id;
                fetch(drivetestConfig.baseUrl + '/admin/api/users.php?action=delete&id=' + id, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(id)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ο χρήστης διαγράφηκε επιτυχώς.');
                        window.location.href = drivetestConfig.baseUrl + '/admin/users.php';
                    } else {
                        alert('Σφάλμα: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Σφάλμα κατά τη διαγραφή χρήστη.');
                });
            }
        });
    });
});

function initMap() {
    console.log('Initializing Google Map');
    if (!window.google || !window.google.maps) {
        console.warn('Google Maps API not loaded yet, retrying...');
        setTimeout(initMap, 500);
        return;
    }

    const defaultLocation = { lat: 40.6401, lng: 22.9444 };
    const lat = parseFloat(document.getElementById("latitude").value) || defaultLocation.lat;
    const lng = parseFloat(document.getElementById("longitude").value) || defaultLocation.lng;

    const map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: lat, lng: lng },
        zoom: 14
    });

    const marker = new google.maps.Marker({
        position: { lat: lat, lng: lng },
        map: map,
        draggable: true
    });

    const autocomplete = new google.maps.places.Autocomplete(document.getElementById("autocomplete"), {
        types: ['geocode']
    });

    autocomplete.addListener("place_changed", function() {
        const place = autocomplete.getPlace();
        if (!place.geometry) {
            alert("Δεν βρέθηκε η τοποθεσία, δοκιμάστε ξανά.");
            return;
        }
        const location = place.geometry.location;
        document.getElementById("latitude").value = location.lat();
        document.getElementById("longitude").value = location.lng();
        marker.setPosition(location);
        map.setCenter(location);

        const addressComponents = place.address_components;
        let street_number = "", route = "", postal_code = "", city = "";
        for (let component of addressComponents) {
            const componentType = component.types[0];
            switch (componentType) {
                case "street_number": street_number = component.long_name; break;
                case "route": route = component.long_name; break;
                case "postal_code": postal_code = component.long_name; break;
                case "locality": city = component.long_name; break;
            }
        }
        document.getElementById("street_number").value = street_number;
        document.getElementById("postal_code").value = postal_code;
        document.getElementById("city").value = city;
        document.getElementById("autocomplete").value = route + " " + street_number;
    });

    google.maps.event.addListener(marker, "dragend", function() {
        const position = marker.getPosition();
        document.getElementById("latitude").value = position.lat();
        document.getElementById("longitude").value = position.lng();
    });
}

function updateFields() {
    const role = document.getElementById('role').value;
    const schoolField = document.getElementById('school-field');
    const schoolSpecificFields = document.getElementById('school-specific-fields');
    const schoolSpecificFieldsResponsible = document.getElementById('school-specific-fields-responsible');

    if (role === 'school') {
        schoolField.style.display = 'none';
        schoolSpecificFields.style.display = 'block';
        schoolSpecificFieldsResponsible.style.display = 'block';
    } else if (role === 'student') {
        schoolField.style.display = 'block';
        schoolSpecificFields.style.display = 'none';
        schoolSpecificFieldsResponsible.style.display = 'none';
    } else {
        schoolField.style.display = 'none';
        schoolSpecificFields.style.display = 'none';
        schoolSpecificFieldsResponsible.style.display = 'none';
    }
}