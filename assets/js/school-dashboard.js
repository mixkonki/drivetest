// Αρχικοποίηση Google Maps
function initMap() {
    // Παίρνουμε τις συντεταγμένες από τα πεδία latitude και longitude
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    
    // Αρχικές προεπιλεγμένες συντεταγμένες (κέντρο της Ελλάδας)
    let latitude = 39.0742;
    let longitude = 21.8243;
    let zoomLevel = 6; // Προεπιλεγμένο επίπεδο ζουμ για όλη την Ελλάδα
    
    // Χρήση των συντεταγμένων από τα πεδία αν υπάρχουν
    if (latitudeInput && latitudeInput.value && longitudeInput && longitudeInput.value) {
        latitude = parseFloat(latitudeInput.value);
        longitude = parseFloat(longitudeInput.value);
        zoomLevel = 15; // Μεγαλύτερο ζουμ για συγκεκριμένη τοποθεσία
    }
    
    // Δημιουργία του χάρτη στο div με id "map"
    const mapElement = document.getElementById('map');
    if (!mapElement) return; // Αν δεν υπάρχει το element, επιστροφή
    
    const map = new google.maps.Map(mapElement, {
        center: { lat: latitude, lng: longitude },
        zoom: zoomLevel,
        mapTypeId: google.maps.MapTypeId.ROADMAP
    });
    
    // Δημιουργία marker για την τοποθεσία
    const marker = new google.maps.Marker({
        position: { lat: latitude, lng: longitude },
        map: map,
        title: document.getElementById('school_name')?.value || 'Σχολή',
        draggable: true // Επιτρέπει στον χρήστη να μετακινήσει το marker
    });
    
    // Ενημέρωση των συντεταγμένων όταν σύρεται το marker
    google.maps.event.addListener(marker, 'dragend', function() {
        const position = marker.getPosition();
        if (latitudeInput) latitudeInput.value = position.lat();
        if (longitudeInput) longitudeInput.value = position.lng();
    });
    
    // Προσθήκη listeners για τα πεδία διεύθυνσης
    const addressFields = ['address', 'street_number', 'postal_code', 'city'];
    addressFields.forEach(field => {
        const input = document.getElementById(field);
        if (input) {
            input.addEventListener('change', function() {
                updateMapFromAddress();
            });
        }
    });
    
    // Προσθήκη κουμπιού ενημέρωσης χάρτη
    const updateMapButton = document.createElement('button');
    updateMapButton.textContent = 'Ενημέρωση Χάρτη';
    updateMapButton.className = 'btn-secondary';
    updateMapButton.style.marginTop = '10px';
    updateMapButton.type = 'button';
    updateMapButton.addEventListener('click', function(e) {
        e.preventDefault();
        updateMapFromAddress();
    });
    
    // Προσθήκη του κουμπιού μετά τον χάρτη
    mapElement.parentNode.insertBefore(updateMapButton, mapElement.nextSibling);
    
    // Συνάρτηση ενημέρωσης του χάρτη από τη διεύθυνση
    function updateMapFromAddress() {
        const address = document.getElementById('address')?.value || '';
        const streetNumber = document.getElementById('street_number')?.value || '';
        const postalCode = document.getElementById('postal_code')?.value || '';
        const city = document.getElementById('city')?.value || '';
        
        if (!address || !city) return; // Αν δεν υπάρχουν βασικά στοιχεία διεύθυνσης, επιστροφή
        
        // Δημιουργία πλήρους διεύθυνσης
        const fullAddress = `${address} ${streetNumber}, ${postalCode} ${city}, Ελλάδα`;
        
        // Χρήση του Geocoder για μετατροπή της διεύθυνσης σε συντεταγμένες
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ 'address': fullAddress }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const location = results[0].geometry.location;
                
                // Ενημέρωση των πεδίων latitude/longitude
                if (latitudeInput) latitudeInput.value = location.lat();
                if (longitudeInput) longitudeInput.value = location.lng();
                
                // Ενημέρωση του χάρτη και του marker
                map.setCenter(location);
                marker.setPosition(location);
                map.setZoom(15);
            } else {
                alert('Η γεωκωδικοποίηση της διεύθυνσης απέτυχε. Παρακαλώ ελέγξτε τα στοιχεία διεύθυνσης.');
            }
        });
    }
}

// Προσθήκη νέων κοινωνικών δικτύων
document.addEventListener('DOMContentLoaded', function() {
    const addSocialBtn = document.getElementById('add-social');
    const socialSelect = document.getElementById('social-select');
    const newSocialContainer = document.getElementById('new-social-container');
    
    if (addSocialBtn && socialSelect && newSocialContainer) {
        addSocialBtn.addEventListener('click', function() {
            const selectedOption = socialSelect.options[socialSelect.selectedIndex];
            if (selectedOption.value) {
                const socialKey = selectedOption.value;
                const socialName = selectedOption.text;
                const socialIcon = selectedOption.getAttribute('data-icon');
                
                const socialDiv = document.createElement('div');
                socialDiv.className = 'social-link';
                socialDiv.innerHTML = `
                    <i class="${socialIcon}"></i>
                    <input type="url" name="social_${socialKey}" class="form-control" 
                           placeholder="URL προφίλ ${socialName}" value="">
                    <button type="button" class="social-delete" onclick="this.parentNode.remove()">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                
                newSocialContainer.appendChild(socialDiv);
                
                // Αφαίρεση από τη λίστα
                socialSelect.removeChild(selectedOption);
                socialSelect.selectedIndex = 0;
            }
        });
    }
    
    // Προσθήκη λειτουργικότητας για το κουμπί ΑΑΔΕ
    const aadeButton = document.querySelector('.aade-button');
    if (aadeButton) {
        aadeButton.addEventListener('click', function(e) {
            e.preventDefault();
            const taxId = document.getElementById('tax_id').value;
            if (!taxId || taxId.length !== 9) {
                alert('Παρακαλώ εισάγετε έναν έγκυρο ΑΦΜ (9 ψηφία)');
                return;
            }
            
            // Υποβολή της φόρμας με το όνομα του κουμπιού
            const form = this.closest('form');
            if (form) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'fetch_aade';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            }
        });
    }
});