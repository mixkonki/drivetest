// @ts-nocheck
// Αρχικοποιεί το JavaScript για τη διαχείριση του προφίλ χρήστη
console.log('User Profile JS loaded - Starting script execution. BASE_URL:', drivetestConfig.baseUrl, 'BASE_PATH inferred:', '<?= BASE_PATH ?>');

// Όταν το document φορτωθεί πλήρως, αρχικοποιεί τα scripts
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event fired, initializing scripts');
    initMap(); // Αρχικοποιεί τον χάρτη άμεσα

    // Αναζητά και ρυθμίζει το κουμπί επεξεργασίας
    const editBtn = document.getElementById('edit-btn');
    if (editBtn) {
        console.log('Edit button found in DOM with ID: edit-btn');
        editBtn.addEventListener('click', function() {
            console.log('Edit button clicked - Switching to edit mode');
            const form = document.querySelector('#user-edit-form');
            if (!form) {
                console.log('User edit form not found, attempting to create dynamically');
                const profileSection = document.querySelector('.profile-section.user-details-column');
                if (profileSection) {
                    profileSection.innerHTML = `
                        <form id="user-edit-form" method="POST" enctype="multipart/form-data" class="profile-section user-details-column">
                            <label for="fullname" class="form-label">Ονοματεπώνυμο:</label>
                            <input type="text" id="fullname" name="fullname" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Ονοματεπώνυμο:')?.parentElement.textContent.replace('Ονοματεπώνυμο:', '').trim() || ''}" required class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" id="email" name="email" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Email:')?.parentElement.textContent.replace('Email:', '').trim() || ''}" required class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                            <label for="phone" class="form-label">Τηλέφωνο:</label>
                            <input type="tel" id="phone" name="phone" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Τηλέφωνο:')?.parentElement.textContent.replace('Τηλέφωνο:', '').trim() || ''}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                            <label for="avatar" class="form-label sr-only">Avatar:</label>
                            <div class="form-group avatar-row">
                                <img src="${document.querySelector('.avatar-upload img')?.src || ''}" class="user-avatar" alt="Avatar">
                                <label for="avatar" class="avatar-upload-btn" style="background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;"><span>Ανέβασμα Avatar</span></label>
                                <input type="file" id="avatar" name="avatar" accept="image/*" class="form-file" style="display: none" onchange="previewAvatar(this.files);">
                            </div>
                            <button type="submit" name="save" class="btn-primary save-btn" style="background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">Αποθήκευση</button>
                        </form>
                    `;
                    console.log('Form created dynamically with HTML:', profileSection.innerHTML);

                    // Προσθήκη event listener
                    const newForm = document.querySelector('#user-edit-form');
                    if (newForm) {
                        console.log('New form found, attaching submit event.');
                        newForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            console.log('Submit event triggered for form:', newForm.outerHTML);
                            const formData = new FormData(newForm);
                            formData.append('action', 'update');
                            formData.append('user_id', userId);

                            console.log('FormData:', Array.from(formData.entries()));
                            console.log('Submitting to URL:', 'http://localhost/drivetest/users/api/profile.php?action=update');

                            fetch('http://localhost/drivetest/users/api/profile.php?action=update', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                console.log('Raw response from server:', data);
                                if (data.success) {
                                    console.log('Update successful with message:', data.message);
                                    alert(data.message);
                                    window.location.href = 'http://localhost/drivetest/users/user_profile.php';
                                } else {
                                    console.error('Update failed with message:', data.message);
                                    alert('Σφάλμα: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Fetch error:', error);
                                alert('Σφάλμα κατά την ενημέρωση του προφίλ. Δες την κονσόλα για λεπτομέρειες.');
                            });
                        });

                        const saveBtn = document.querySelector('.save-btn');
                        if (saveBtn) {
                            console.log('Save button found and shown:', saveBtn.style.display);
                            saveBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                console.log('Save button clicked, triggering submit.');
                                const formToSubmit = document.querySelector('#user-edit-form');
                                if (formToSubmit) {
                                    formToSubmit.dispatchEvent(new Event('submit'));
                                } else {
                                    console.error('Form not found when save button clicked.');
                                    alert('Σφάλμα: Η φόρμα δεν βρέθηκε.');
                                }
                            });
                        } else {
                            console.error('Save button not found in DOM.');
                            alert('Σφάλμα: Το κουμπί Αποθήκευσης δεν βρέθηκε.');
                        }
                    } else {
                        console.error('New form not found after creation.');
                        alert('Σφάλμα: Η φόρμα δεν δημιουργήθηκε σωστά.');
                    }
                } else {
                    console.error('Profile section not found.');
                    alert('Σφάλμα: Η ενότητα προφίλ δεν βρέθηκε.');
                }
            } else {
                console.log('User edit form already exists in DOM');
            }

            // Ενημερώνει τον χάρτη και τη διεύθυνση
            const addressSection = document.querySelector('.profile-section.address-column');
            if (addressSection) {
                addressSection.innerHTML = `
                    <form class="profile-section address-column">
                        <label for="autocomplete" class="form-label">Διεύθυνση:</label>
                        <input type="text" id="autocomplete" name="address" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Διεύθυνση:')?.parentElement.textContent.replace('Διεύθυνση:', '').trim().replace('Δεν έχει οριστεί', '') || ''}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="street_number" class="form-label">Αριθμός Οδού:</label>
                        <input type="text" id="street_number" name="street_number" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Αριθμός Οδού:')?.parentElement.textContent.replace('Αριθμός Οδού:', '').trim().replace('Δεν έχει οριστεί', '') || ''}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="postal_code" class="form-label">Ταχυδρομικός Κώδικας:</label>
                        <input type="text" id="postal_code" name="postal_code" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Ταχυδρομικός Κώδικας:')?.parentElement.textContent.replace('Ταχυδρομικός Κώδικας:', '').trim().replace('Δεν έχει οριστεί', '') || ''}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="city" class="form-label">Πόλη:</label>
                        <input type="text" id="city" name="city" value="${Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === 'Πόλη:')?.parentElement.textContent.replace('Πόλη:', '').trim().replace('Δεν έχει οριστεί', '') || ''}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <input type="hidden" id="latitude" name="latitude" value="${document.querySelector('input#latitude')?.value || ''}">
                        <input type="hidden" id="longitude" name="longitude" value="${document.querySelector('input#longitude')?.value || ''}">
                    </form>
                `;
                console.log('Address fields created with HTML:', addressSection.innerHTML);
                initAutocomplete();
            } else {
                console.error('Address section not found in DOM');
                alert('Σφάλμα: Η ενότητα διεύθυνσης δεν βρέθηκε.');
            }

            editBtn.style.display = 'none'; // Κρύβει το κουμπί Επεξεργασία
            updateMap(); // Ενημερώνει τον χάρτη
        });
    } else {
        console.error("Edit button not found in DOM");
        alert("Σφάλμα: Το κουμπί Επεξεργασίας δεν βρέθηκε.");
    }

    // Ελέγχει αν το jQuery φορτώθηκε σωστά
    if (typeof jQuery === 'undefined') {
        console.error('jQuery not loaded. Check internet connection or script tag in user_profile.php');
    } else {
        console.log('jQuery loaded successfully, version:', jQuery.fn.jquery);
    }
});

// Αρχικοποιεί τον χάρτη Google Maps
function initMap() {
    console.log('Initializing Google Map for user profile');
    if (!window.google || !window.google.maps) {
        console.warn('Google Maps API not loaded yet, retrying in 500ms...');
        setTimeout(initMap, 500);
        return;
    }

    const defaultLocation = { lat: 40.6401, lng: 22.9444 }; // Θεσσαλονίκη
    const lat = parseFloat(document.getElementById('latitude')?.value) || defaultLocation.lat;
    const lng = parseFloat(document.getElementById('longitude')?.value) || defaultLocation.lng;

    console.log('Map initialization with coordinates - Lat:', lat, 'Lng:', lng);
    window.map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: lat, lng: lng },
        zoom: 14
    });

    window.marker = new google.maps.Marker({
        position: { lat: lat, lng: lng },
        map: window.map,
        draggable: true
    });
    console.log('Marker placed on map at:', { lat: lat, lng: lng });

    // Ενημερώνει τις συντεταγμένες όταν μετακινείται το marker
    google.maps.event.addListener(window.marker, 'dragend', function() {
        const position = window.marker.getPosition();
        document.getElementById('latitude').value = position.lat();
        document.getElementById('longitude').value = position.lng();
        console.log('Marker dragged to - Lat:', position.lat(), 'Lng:', position.lng());
        updateMapFromLatLng(position.lat(), position.lng());
    });
}

// Αρχικοποιεί το Autocomplete του Google Maps για τη διεύθυνση
function initAutocomplete() {
    console.log('Initializing Autocomplete for address field');
    const autocomplete = new google.maps.places.Autocomplete(document.getElementById('autocomplete'), {
        types: ['geocode']
    });

    autocomplete.addListener('place_changed', function() {
        console.log('Place changed event triggered in Autocomplete');
        const place = autocomplete.getPlace();
        if (!place.geometry) {
            console.error('No geometry found for selected place');
            alert('Δεν βρέθηκε η τοποθεσία, δοκιμάστε ξανά.');
            return;
        }
        const location = place.geometry.location;
        console.log('Selected place location:', location);
        document.getElementById('latitude').value = location.lat();
        document.getElementById('longitude').value = location.lng();
        window.marker.setPosition(location);
        window.map.setCenter(location);

        const addressComponents = place.address_components;
        let street_number = '', route = '', postal_code = '', city = '';
        for (let component of addressComponents) {
            const componentType = component.types[0];
            switch (componentType) {
                case 'street_number': street_number = component.long_name; break;
                case 'route': route = component.long_name; break;
                case 'postal_code': postal_code = component.long_name; break;
                case 'locality': city = component.long_name; break;
            }
        }
        document.getElementById('street_number').value = street_number;
        document.getElementById('postal_code').value = postal_code;
        document.getElementById('city').value = city;
        document.getElementById('autocomplete').value = route + ' ' + street_number;
        console.log('Updated form fields - Street Number:', street_number, 'Postal Code:', postal_code, 'City:', city, 'Address:', route + ' ' + street_number);
    });
}

// Ενημερώνει τον χάρτη με νέα διεύθυνση
function updateMap() {
    console.log('Updating map manually with user address...');
    const address = document.getElementById('autocomplete')?.value || '';
    const streetNumber = document.getElementById('street_number')?.value || '';
    const city = document.getElementById('city')?.value || '';
    const postalCode = document.getElementById('postal_code')?.value || '';

    console.log('Full address for geocoding:', `${address} ${streetNumber}, ${city}, ${postalCode}, Ελλάδα`);
    const fullAddress = `${address} ${streetNumber}, ${city}, ${postalCode}, Ελλάδα`;
    const geocoder = new google.maps.Geocoder();

    geocoder.geocode({ address: fullAddress }, (results, status) => {
        console.log('Geocode status for user address:', status);
        if (status === 'OK' && results[0]) {
            const location = results[0].geometry.location;
            if (window.map && typeof window.map.setCenter === 'function') {
                window.map.setCenter(location);
                window.marker.setPosition(location);
                console.log('Geocoding success - New location:', location);

                if (document.getElementById('latitude')) {
                    document.getElementById('latitude').value = location.lat();
                    console.log('Latitude updated in form:', location.lat());
                }
                if (document.getElementById('longitude')) {
                    document.getElementById('longitude').value = location.lng();
                    console.log('Longitude updated in form:', location.lng());
                }
                alert('Χάρτης ενημερώθηκε με επιτυχία με τη νέα τοποθεσία: ' + fullAddress);
            } else {
                console.error('Map not initialized in updateMap');
                alert('Σφάλμα: Ο χάρτης δεν έχει αρχικοποιηθεί. Παρακαλώ ανανεώστε τη σελίδα.');
            }
        } else {
            console.error('Geocode failed for user address:', status);
            alert('Απέτυχε η γεωκωδικοποίηση της διεύθυνσης: ' + status);
        }
    });
}

// Ενημερώνει τον χάρτη με νέες συντεταγμένες
function updateMapFromLatLng(lat, lng) {
    console.log('Updating map from latitude/longitude - Lat:', lat, 'Lng:', lng);
    const location = { lat: lat, lng: lng };
    if (window.map && typeof window.map.setCenter === 'function') {
        window.map.setCenter(location);
        window.marker.setPosition(location);
        console.log('Map updated with new location:', location);
        alert('Χάρτης ενημερώθηκε με επιτυχία με τη νέα τοποθεσία.');
    } else {
        console.error('Map not initialized in updateMapFromLatLng');
        alert('Σφάλμα: Ο χάρτης δεν έχει αρχικοποιηθεί. Παρακαλώ ανανεώστε τη σελίδα.');
    }
}

// Προεπισκόπηση του avatar πριν το ανέβασμα
function previewAvatar(files) {
    console.log('Previewing avatar... Files:', files);
    if (files && files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('.user-avatar').src = e.target.result;
            console.log('Avatar preview loaded with URL:', e.target.result);
            alert('Προεπισκόπηση avatar ενημερώθηκε!');
        };
        reader.readAsDataURL(files[0]);
    } else {
        console.log('No files selected for avatar preview');
        alert('Δεν επιλέχθηκε αρχείο για προεπισκόπηση avatar.');
    }
}