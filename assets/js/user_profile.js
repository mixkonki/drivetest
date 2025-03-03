// @ts-nocheck
document.addEventListener('DOMContentLoaded', function() {
    function checkGoogleMapsLoaded() {
        if (window.google && window.google.maps) {
            initMap();
        } else {
            setTimeout(checkGoogleMapsLoaded, 500);
        }
    }
    
    checkGoogleMapsLoaded();
    
    const editBtn = document.getElementById('edit-btn');
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            const profileSection = document.querySelector('.profile-section.user-details-column');
            if (profileSection) {
                profileSection.innerHTML = `
                    <form id="user-edit-form" method="POST" enctype="multipart/form-data" class="profile-section user-details-column">
                        <h3>Επεξεργασία Στοιχείων</h3>
                        <label for="fullname" class="form-label">Ονοματεπώνυμο:</label>
                        <input type="text" id="fullname" name="fullname" value="${getProfileValue('Ονοματεπώνυμο:')}" required class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" id="email" name="email" value="${getProfileValue('Email:')}" required class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="phone" class="form-label">Τηλέφωνο:</label>
                        <input type="tel" id="phone" name="phone" value="${getProfileValue('Τηλέφωνο:')}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="avatar" class="form-label sr-only">Avatar:</label>
                        <div class="form-group avatar-row">
                            <img src="${document.querySelector('.avatar-upload img')?.src || ''}" class="user-avatar" alt="Avatar">
                            <label for="avatar" class="avatar-upload-btn" style="background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;"><span>Ανέβασμα Avatar</span></label>
                            <input type="file" id="avatar" name="avatar" accept="image/*" class="form-file" style="display: none" onchange="previewAvatar(this.files);">
                        </div>
                        
                        <input type="hidden" id="hidden_address" name="hidden_address" value="${getProfileValue('Διεύθυνση:')}">
                        <input type="hidden" id="hidden_street_number" name="hidden_street_number" value="${getProfileValue('Αριθμός Οδού:')}">
                        <input type="hidden" id="hidden_postal_code" name="hidden_postal_code" value="${getProfileValue('Ταχυδρομικός Κώδικας:')}">
                        <input type="hidden" id="hidden_city" name="hidden_city" value="${getProfileValue('Πόλη:')}">
                        <input type="hidden" id="hidden_latitude" name="hidden_latitude" value="${document.querySelector('input#latitude')?.value || ''}">
                        <input type="hidden" id="hidden_longitude" name="hidden_longitude" value="${document.querySelector('input#longitude')?.value || ''}">
                        
                        <button type="submit" name="save" class="btn-primary save-btn" style="background-color: #aa3636; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">Αποθήκευση</button>
                    </form>
                `;
            }
            
            const addressSection = document.querySelector('.profile-section.address-column');
            if (addressSection) {
                addressSection.innerHTML = `
                    <form id="address-form" class="profile-section address-column">
                        <h3>Επεξεργασία Διεύθυνσης</h3>
                        <label for="autocomplete" class="form-label">Διεύθυνση:</label>
                        <input type="text" id="autocomplete" name="address" value="${getProfileValue('Διεύθυνση:')}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="street_number" class="form-label">Αριθμός Οδού:</label>
                        <input type="text" id="street_number" name="street_number" value="${getProfileValue('Αριθμός Οδού:')}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="postal_code" class="form-label">Ταχυδρομικός Κώδικας:</label>
                        <input type="text" id="postal_code" name="postal_code" value="${getProfileValue('Ταχυδρομικός Κώδικας:')}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><br>
                        <label for="city" class="form-label">Πόλη:</label>
                        <input type="text" id="city" name="city" value="${getProfileValue('Πόλη:')}" class="form-input" style="background: #fff; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <input type="hidden" id="latitude" name="latitude" value="${document.querySelector('input#latitude')?.value || ''}">
                        <input type="hidden" id="longitude" name="longitude" value="${document.querySelector('input#longitude')?.value || ''}">
                    </form>
                `;
                
                setTimeout(function() {
                    initAutocomplete();
                }, 500);
            }
            
            const userEditForm = document.getElementById('user-edit-form');
            if (userEditForm) {
                userEditForm.setAttribute('action', `${drivetestConfig.baseUrl}/users/profile_update.php`);
            }
            
            const saveBtn = document.querySelector('.save-btn');
            if (saveBtn) {
                saveBtn.style.display = 'block';
            }
            
            editBtn.style.display = 'none';
            
            setupFormSubmitHandler();
            
            updateMap();
        });
    } else {
        alert("Σφάλμα: Το κουμπί Επεξεργασίας δεν βρέθηκε.");
    }
});

function setupFormSubmitHandler() {
    const userEditForm = document.getElementById('user-edit-form');
    if (!userEditForm) {
        return;
    }
    
    $(userEditForm).on('submit', function(e) {
        e.preventDefault();
        
        const saveButton = document.querySelector('.save-btn');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Αποθήκευση...';
        }
        
        const formData = new FormData(userEditForm);
        
        const addressForm = document.getElementById('address-form');
        if (addressForm) {
            const addressInputs = addressForm.querySelectorAll('input');
            addressInputs.forEach(input => {
                if (input.name) {
                    formData.append(input.name, input.value || '');
                    
                    const hiddenField = document.getElementById('hidden_' + input.name);
                    if (hiddenField) {
                        hiddenField.value = input.value || '';
                    }
                }
            });
        }
        
        formData.append('action', 'update');
        formData.append('user_id', userId);
        
        const apiUrl = `${drivetestConfig.baseUrl}/users/api/profile.php`;
        
        $.ajax({
            url: apiUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Το προφίλ ενημερώθηκε με επιτυχία!');
                    window.location.reload();
                } else {
                    userEditForm.submit();
                    
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.textContent = 'Αποθήκευση';
                    }
                }
            },
            error: function(xhr, status, error) {
                userEditForm.submit();
            }
        });
    });
    
    const saveBtn = document.querySelector('.save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            $(userEditForm).submit();
        });
    }
}

function initMap() {
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        return;
    }
    
    const defaultLocation = { lat: 40.6401, lng: 22.9444 };
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const lat = latInput && latInput.value ? parseFloat(latInput.value) : defaultLocation.lat;
    const lng = lngInput && lngInput.value ? parseFloat(lngInput.value) : defaultLocation.lng;
    
    try {
        window.map = new google.maps.Map(mapElement, {
            center: { lat: lat, lng: lng },
            zoom: 14
        });
        
        window.marker = new google.maps.Marker({
            position: { lat: lat, lng: lng },
            map: window.map,
            draggable: true
        });
        
        google.maps.event.addListener(window.marker, 'dragend', function() {
            const position = window.marker.getPosition();
            const latElem = document.getElementById('latitude');
            const lngElem = document.getElementById('longitude');
            
            if (latElem) latElem.value = position.lat();
            if (lngElem) lngElem.value = position.lng();
            
            updateMapFromLatLng(position.lat(), position.lng());
        });
    } catch (e) {
        console.error('Error initializing map:', e);
    }
}

function initAutocomplete() {
    const autocompleteInput = document.getElementById('autocomplete');
    
    if (!autocompleteInput) {
        return;
    }
    
    if (!window.google || !window.google.maps || !window.google.maps.places) {
        setTimeout(initAutocomplete, 500);
        return;
    }
    
    try {
        const autocomplete = new google.maps.places.Autocomplete(autocompleteInput, {
            types: ['geocode']
        });
        
        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            
            if (!place.geometry) {
                alert('Δεν βρέθηκε η τοποθεσία, δοκιμάστε ξανά.');
                return;
            }
            
            const location = place.geometry.location;
            
            const latElem = document.getElementById('latitude');
            const lngElem = document.getElementById('longitude');
            
            if (latElem) latElem.value = location.lat();
            if (lngElem) lngElem.value = location.lng();
            
            const hiddenLatElem = document.getElementById('hidden_latitude');
            const hiddenLngElem = document.getElementById('hidden_longitude');
            
            if (hiddenLatElem) hiddenLatElem.value = location.lat();
            if (hiddenLngElem) hiddenLngElem.value = location.lng();
            
            if (window.map && window.marker) {
                window.marker.setPosition(location);
                window.map.setCenter(location);
            }
            
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
            
            const streetNumberElem = document.getElementById('street_number');
            const postalCodeElem = document.getElementById('postal_code');
            const cityElem = document.getElementById('city');
            
            if (streetNumberElem) streetNumberElem.value = street_number;
            if (postalCodeElem) postalCodeElem.value = postal_code;
            if (cityElem) cityElem.value = city;
            if (autocompleteInput) autocompleteInput.value = route;
            
            const hiddenAddressElem = document.getElementById('hidden_address');
            const hiddenStreetNumberElem = document.getElementById('hidden_street_number');
            const hiddenPostalCodeElem = document.getElementById('hidden_postal_code');
            const hiddenCityElem = document.getElementById('hidden_city');
            
            if (hiddenAddressElem) hiddenAddressElem.value = route;
            if (hiddenStreetNumberElem) hiddenStreetNumberElem.value = street_number;
            if (hiddenPostalCodeElem) hiddenPostalCodeElem.value = postal_code;
            if (hiddenCityElem) hiddenCityElem.value = city;
        });
    } catch (e) {
        console.error('Error initializing autocomplete:', e);
    }
}

function updateMap() {
    const address = document.getElementById('autocomplete')?.value || '';
    const streetNumber = document.getElementById('street_number')?.value || '';
    const city = document.getElementById('city')?.value || '';
    const postalCode = document.getElementById('postal_code')?.value || '';
    
    if (!address && !city) {
        return;
    }
    
    const fullAddress = `${address} ${streetNumber}, ${city}, ${postalCode}, Ελλάδα`;
    
    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: fullAddress }, (results, status) => {
        if (status === 'OK' && results && results[0]) {
            const location = results[0].geometry.location;
            
            if (window.map && typeof window.map.setCenter === 'function') {
                window.map.setCenter(location);
                window.marker.setPosition(location);
                
                const latElem = document.getElementById('latitude');
                const lngElem = document.getElementById('longitude');
                
                if (latElem) {
                    latElem.value = location.lat();
                }
                
                if (lngElem) {
                    lngElem.value = location.lng();
                }
                
                const hiddenLatElem = document.getElementById('hidden_latitude');
                const hiddenLngElem = document.getElementById('hidden_longitude');
                
                if (hiddenLatElem) hiddenLatElem.value = location.lat();
                if (hiddenLngElem) hiddenLngElem.value = location.lng();
            } else {
                console.error('Map not initialized in updateMap');
            }
        } else {
            console.error('Geocode failed for user address:', status);
        }
    });
}

function updateMapFromLatLng(lat, lng) {
    const location = { lat: lat, lng: lng };
    
    if (window.map && typeof window.map.setCenter === 'function') {
        window.map.setCenter(location);
        window.marker.setPosition(location);
        
        const hiddenLatElem = document.getElementById('hidden_latitude');
        const hiddenLngElem = document.getElementById('hidden_longitude');
        
        if (hiddenLatElem) hiddenLatElem.value = lat;
        if (hiddenLngElem) hiddenLngElem.value = lng;
        
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: location }, (results, status) => {
            if (status === 'OK' && results && results[0]) {
                const addressComponents = results[0].address_components;
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
                
                const autocompleteElem = document.getElementById('autocomplete');
                const streetNumberElem = document.getElementById('street_number');
                const postalCodeElem = document.getElementById('postal_code');
                const cityElem = document.getElementById('city');
                
                if (autocompleteElem) autocompleteElem.value = route;
                if (streetNumberElem) streetNumberElem.value = street_number;
                if (postalCodeElem) postalCodeElem.value = postal_code;
                if (cityElem) cityElem.value = city;
                
                const hiddenAddressElem = document.getElementById('hidden_address');
                const hiddenStreetNumberElem = document.getElementById('hidden_street_number');
                const hiddenPostalCodeElem = document.getElementById('hidden_postal_code');
                const hiddenCityElem = document.getElementById('hidden_city');
                
                if (hiddenAddressElem) hiddenAddressElem.value = route;
                if (hiddenStreetNumberElem) hiddenStreetNumberElem.value = street_number;
                if (hiddenPostalCodeElem) hiddenPostalCodeElem.value = postal_code;
                if (hiddenCityElem) hiddenCityElem.value = city;
            } else {
                console.error('Reverse geocoding failed:', status);
            }
        });
    } else {
        console.error('Map not initialized in updateMapFromLatLng');
    }
}

function previewAvatar(files) {
    if (files && files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatarImg = document.querySelector('.user-avatar');
            if (avatarImg) {
                avatarImg.src = e.target.result;
            }
        };
        reader.readAsDataURL(files[0]);
    }
}

function getProfileValue(label) {
    const element = Array.from(document.querySelectorAll('p strong')).find(el => el.textContent.trim() === label);
    if (element && element.parentElement) {
        return element.parentElement.textContent.replace(label, '').trim().replace('Δεν έχει οριστεί', '');
    }
    return '';
}