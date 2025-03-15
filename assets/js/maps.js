/**
 * DriveTest - Google Maps Functionality
 * Περιέχει τις λειτουργίες για τους χάρτες Google Maps στην εφαρμογή
 */

// Έλεγχος φόρτωσης του Google Maps API
if (typeof google === 'undefined') {
    console.error('Google Maps API δεν φορτώθηκε σωστά');
}

/**
 * Αρχικοποίηση απλού χάρτη με marker
 * 
 * @param {string} elementId - Το ID του HTML element όπου θα εμφανιστεί ο χάρτης
 * @param {number} lat - Το γεωγραφικό πλάτος
 * @param {number} lng - Το γεωγραφικό μήκος
 * @param {number} zoom - Το επίπεδο zoom (προαιρετικό, προεπιλογή: 15)
 * @param {boolean} draggable - Αν ο marker θα μπορεί να μετακινηθεί (προαιρετικό, προεπιλογή: false)
 * @returns {object} - Αντικείμενο με τον χάρτη και τον marker
 */
function initMap(elementId, lat, lng, zoom = 15, draggable = false) {
    const mapElement = document.getElementById(elementId);
    
    if (!mapElement) {
        console.error(`Element με ID ${elementId} δεν βρέθηκε`);
        return null;
    }
    
    // Έλεγχος αν υπάρχουν έγκυρες συντεταγμένες
    if (isNaN(lat) || isNaN(lng)) {
        console.warn('Μη έγκυρες συντεταγμένες. Χρήση προεπιλεγμένων συντεταγμένων (Αθήνα)');
        lat = 37.9838;
        lng = 23.7275;
        zoom = 7;
    }
    
    // Δημιουργία χάρτη
    const map = new google.maps.Map(mapElement, {
        center: { lat, lng },
        zoom: zoom,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true
    });
    
    // Δημιουργία marker
    const marker = new google.maps.Marker({
        position: { lat, lng },
        map: map,
        draggable: draggable,
        animation: google.maps.Animation.DROP
    });
    
    return { map, marker };
}

/**
 * Αρχικοποίηση χάρτη με δυνατότητα επεξεργασίας
 * 
 * @param {string} mapElementId - Το ID του HTML element όπου θα εμφανιστεί ο χάρτης
 * @param {string} latInputId - Το ID του input πεδίου για το γεωγραφικό πλάτος
 * @param {string} lngInputId - Το ID του input πεδίου για το γεωγραφικό μήκος
 * @param {number|null} initialLat - Αρχικό γεωγραφικό πλάτος (προαιρετικό)
 * @param {number|null} initialLng - Αρχικό γεωγραφικό μήκος (προαιρετικό)
 * @returns {object} - Αντικείμενο με τον χάρτη και τον marker
 */
function initEditableMap(mapElementId, latInputId, lngInputId, initialLat = null, initialLng = null) {
    // Λήψη των στοιχείων του DOM
    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    
    if (!latInput || !lngInput) {
        console.error('Τα πεδία για τις συντεταγμένες δεν βρέθηκαν');
        return null;
    }
    
    // Λήψη των αρχικών συντεταγμένων
    const lat = initialLat !== null ? initialLat : (latInput.value ? parseFloat(latInput.value) : null);
    const lng = initialLng !== null ? initialLng : (lngInput.value ? parseFloat(lngInput.value) : null);
    
    // Αρχικοποίηση χάρτη
    const mapData = initMap(mapElementId, lat, lng, 15, true);
    
    if (!mapData) return null;
    
    const { map, marker } = mapData;
    
    // Ενημέρωση των συντεταγμένων όταν μετακινείται ο marker
    marker.addListener('dragend', function() {
        const position = marker.getPosition();
        latInput.value = position.lat();
        lngInput.value = position.lng();
    });
    
    // Ενημέρωση του marker αν αλλάξουν τα input πεδία
    latInput.addEventListener('change', updateMarkerFromInputs);
    lngInput.addEventListener('change', updateMarkerFromInputs);
    
    function updateMarkerFromInputs() {
        const newLat = parseFloat(latInput.value);
        const newLng = parseFloat(lngInput.value);
        
        if (!isNaN(newLat) && !isNaN(newLng)) {
            const newPosition = new google.maps.LatLng(newLat, newLng);
            marker.setPosition(newPosition);
            map.setCenter(newPosition);
        }
    }
    
    return { map, marker };
}

/**
 * Ενημέρωση χάρτη με βάση τα πεδία διεύθυνσης
 * 
 * @param {object} mapData - Αντικείμενο με τον χάρτη και τον marker
 * @param {string} addressInputId - Το ID του input πεδίου για τη διεύθυνση
 * @param {string} streetNumberInputId - Το ID του input πεδίου για τον αριθμό
 * @param {string} postalCodeInputId - Το ID του input πεδίου για τον ΤΚ
 * @param {string} cityInputId - Το ID του input πεδίου για την πόλη
 * @param {string} latInputId - Το ID του input πεδίου για το γεωγραφικό πλάτος
 * @param {string} lngInputId - Το ID του input πεδίου για το γεωγραφικό μήκος
 */
function setupAddressToMapUpdates(
    mapData, 
    addressInputId, 
    streetNumberInputId, 
    postalCodeInputId, 
    cityInputId, 
    latInputId, 
    lngInputId
) {
    const addressInput = document.getElementById(addressInputId);
    const streetNumberInput = document.getElementById(streetNumberInputId);
    const postalCodeInput = document.getElementById(postalCodeInputId);
    const cityInput = document.getElementById(cityInputId);
    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    
    if (!addressInput || !streetNumberInput || !postalCodeInput || !cityInput || !latInput || !lngInput) {
        console.error('Τα πεδία διεύθυνσης δεν βρέθηκαν');
        return;
    }
    
    if (!mapData || !mapData.map || !mapData.marker) {
        console.error('Ο χάρτης δεν έχει αρχικοποιηθεί σωστά');
        return;
    }
    
    const { map, marker } = mapData;
    const geocoder = new google.maps.Geocoder();
    
    const updateMap = function() {
        const address = `${addressInput.value} ${streetNumberInput.value}, ${postalCodeInput.value} ${cityInput.value}, Greece`;
        
        if (address.trim() === ', , Greece') return;
        
        geocoder.geocode({ address }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const position = results[0].geometry.location;
                map.setCenter(position);
                marker.setPosition(position);
                
                latInput.value = position.lat();
                lngInput.value = position.lng();
            } else {
                console.warn('Geocode was not successful for the following reason: ' + status);
            }
        });
    };
    
    // Ενημέρωση του χάρτη με καθυστέρηση για να αποφύγουμε πολλά requests
    let timeoutId;
    const handleAddressChange = function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(updateMap, 1000);
    };
    
    addressInput.addEventListener('change', handleAddressChange);
    streetNumberInput.addEventListener('change', handleAddressChange);
    postalCodeInput.addEventListener('change', handleAddressChange);
    cityInput.addEventListener('change', handleAddressChange);
}

/**
 * Προσθήκη αυτόματης συμπλήρωσης σε πεδίο διεύθυνσης
 * 
 * @param {string} inputId - Το ID του input πεδίου
 * @param {function} callback - Callback function που καλείται όταν επιλεγεί μια διεύθυνση
 */
function setupAddressAutocomplete(inputId, callback) {
    const input = document.getElementById(inputId);
    
    if (!input) {
        console.error(`Element με ID ${inputId} δεν βρέθηκε`);
        return;
    }
    
    const options = {
        componentRestrictions: { country: 'gr' }
    };
    
    const autocomplete = new google.maps.places.Autocomplete(input, options);
    
    autocomplete.addListener('place_changed', function() {
        const place = autocomplete.getPlace();
        
        if (!place.geometry) {
            console.warn('Δεν υπάρχουν λεπτομέρειες για τη διεύθυνση που επιλέχθηκε');
            return;
        }
        
        if (typeof callback === 'function') {
            callback(place);
        }
    });
}