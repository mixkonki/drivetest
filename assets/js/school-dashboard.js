/**
 * DriveTest - School Dashboard JavaScript
 * Περιέχει τις λειτουργίες για τον πίνακα ελέγχου σχολής
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log("School Dashboard JS loaded");
    
    // Αρχικοποίηση Google Maps
    initSchoolMap();
    
    // Προσθήκη λειτουργικότητας για το κουμπί προσθήκης κοινωνικών δικτύων
    initSocialLinksHandlers();
});

/**
 * Αρχικοποίηση του χάρτη Google Maps
 */
function initSchoolMap() {
    // Παίρνουμε τις συντεταγμένες από τα πεδία latitude και longitude
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    
    if (!latitudeInput || !longitudeInput || !google || !google.maps) {
        console.error('Google Maps not loaded or coordinates not found');
        return;
    }
    
    // Αρχικές προεπιλεγμένες συντεταγμένες (κέντρο της Ελλάδας)
    let latitude = 39.0742;
    let longitude = 21.8243;
    let zoomLevel = 6; // Προεπιλεγμένο επίπεδο ζουμ για όλη την Ελλάδα
    
    // Χρήση των συντεταγμένων από τα πεδία αν υπάρχουν
    if (latitudeInput.value && longitudeInput.value) {
        latitude = parseFloat(latitudeInput.value);
        longitude = parseFloat(longitudeInput.value);
        zoomLevel = 15; // Μεγαλύτερο ζουμ για συγκεκριμένη τοποθεσία
    }
    
    // Δημιουργία του χάρτη στο div με id "map"
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        console.error('Map element not found');
        return;
    }
    
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
        latitudeInput.value = position.lat();
        longitudeInput.value = position.lng();
    });
    
    // Προσθήκη listeners για τα πεδία διεύθυνσης
    const addressFields = ['address', 'street_number', 'postal_code', 'city'];
    addressFields.forEach(field => {
        const input = document.getElementById(field);
        if (input) {
            input.addEventListener('change', function() {
                updateMapFromAddress(map, marker);
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
        updateMapFromAddress(map, marker);
    });
    
    // Προσθήκη του κουμπιού μετά τον χάρτη
    mapElement.parentNode.insertBefore(updateMapButton, mapElement.nextSibling);
}

/**
 * Ενημέρωση του χάρτη από τη διεύθυνση
 * 
 * @param {google.maps.Map} map - Ο χάρτης Google Maps
 * @param {google.maps.Marker} marker - Ο δείκτης στον χάρτη
 */
function updateMapFromAddress(map, marker) {
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
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            
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

/**
 * Αρχικοποίηση των χειριστών για τα κοινωνικά δίκτυα
 */
function initSocialLinksHandlers() {
    console.log("Initializing social links handlers");
    
    const addSocialBtn = document.getElementById('add-social');
    const socialSelect = document.getElementById('social-select');
    const newSocialContainer = document.getElementById('new-social-container');
    
    if (!addSocialBtn || !socialSelect || !newSocialContainer) {
        console.error('Social network elements not found:', 
            { addSocialBtn: !!addSocialBtn, socialSelect: !!socialSelect, newSocialContainer: !!newSocialContainer });
        return;
    }
    
    addSocialBtn.addEventListener('click', function() {
        console.log("Add social button clicked");
        
        const selectedIndex = socialSelect.selectedIndex;
        if (selectedIndex <= 0) {
            console.log("No option selected or first option selected");
            return;
        }
        
        const selectedOption = socialSelect.options[selectedIndex];
        const socialKey = selectedOption.value;
        const socialName = selectedOption.text;
        const socialIcon = selectedOption.getAttribute('data-icon');
        
        console.log("Selected social:", { socialKey, socialName, socialIcon });
        
        if (!socialKey) {
            console.log("Invalid social key");
            return;
        }
        
        const socialDiv = document.createElement('div');
        socialDiv.className = 'social-link';
        socialDiv.innerHTML = `
            <i class="${socialIcon}"></i>
            <input type="url" name="social_${socialKey}" class="form-control" 
                   placeholder="URL προφίλ ${socialName}" value="">
            <button type="button" class="social-delete" onclick="removeSocialLink(this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        newSocialContainer.appendChild(socialDiv);
        
        // Αφαίρεση από τη λίστα
        socialSelect.removeChild(selectedOption);
        socialSelect.selectedIndex = 0;
    });
}

/**
 * Αφαίρεση κοινωνικού δικτύου
 * 
 * @param {HTMLElement} element - Το στοιχείο κουμπιού που κλήθηκε
 */
function removeSocialLink(element) {
    const socialLink = element.closest('.social-link');
    if (socialLink) {
        socialLink.remove();
    }
}