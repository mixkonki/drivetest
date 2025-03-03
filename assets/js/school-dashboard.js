// Αρχείο JavaScript για το dashboard της σχολής

// Αρχικοποίηση του χάρτη
let map;
let marker;

function initMap() {
    const mapElement = document.getElementById("map");
    if (!mapElement) return;
    
    // Λήψη συντεταγμένων από data attributes
    const lat = parseFloat(mapElement.getAttribute('data-lat') || 38.9);
    const lng = parseFloat(mapElement.getAttribute('data-lng') || 22.9);
    const hasCoordinates = mapElement.hasAttribute('data-has-coordinates');
    
    if (hasCoordinates) {
        const schoolLocation = { lat, lng };
        map = new google.maps.Map(mapElement, {
            zoom: 15,
            center: schoolLocation,
        });
        marker = new google.maps.Marker({
            position: schoolLocation,
            map: map,
            title: document.getElementById('school_name').value || 'Τοποθεσία Σχολής'
        });
    } else {
        // Αν δεν υπάρχουν συντεταγμένες, εμφάνιση χάρτη της Ελλάδας
        const defaultLocation = { lat: 38.9, lng: 22.9 };
        map = new google.maps.Map(mapElement, {
            zoom: 6,
            center: defaultLocation,
        });
    }
    
    // Προσθήκη listener για ενημέρωση του χάρτη όταν αλλάζει η διεύθυνση
    document.getElementById('address')?.addEventListener('change', updateMap);
    document.getElementById('street_number')?.addEventListener('change', updateMap);
    document.getElementById('postal_code')?.addEventListener('change', updateMap);
    document.getElementById('city')?.addEventListener('change', updateMap);
}

function updateMap() {
    const address = document.getElementById('address').value;
    const streetNumber = document.getElementById('street_number').value;
    const postalCode = document.getElementById('postal_code').value;
    const city = document.getElementById('city').value;
    
    if (address && city) {
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ 
            'address': address + ' ' + streetNumber + ', ' + city + ', ' + postalCode + ', Ελλάδα' 
        }, function(results, status) {
            if (status === 'OK') {
                map.setCenter(results[0].geometry.location);
                map.setZoom(15);
                
                if (marker) {
                    marker.setMap(null);
                }
                
                marker = new google.maps.Marker({
                    map: map,
                    position: results[0].geometry.location,
                    title: document.getElementById('school_name').value || 'Τοποθεσία Σχολής'
                });
            }
        });
    }
}

// Διαχείριση κοινωνικών δικτύων
document.addEventListener('DOMContentLoaded', function() {
    // Προσθήκη νέου κοινωνικού δικτύου
    const addSocialButton = document.getElementById('add-social');
    if (addSocialButton) {
        addSocialButton.addEventListener('click', addSocialNetwork);
    }
    
    // Διαγραφή κοινωνικών δικτύων (για τα υπάρχοντα)
    const deleteSocialButtons = document.querySelectorAll('.social-delete');
    deleteSocialButtons.forEach(button => {
        button.addEventListener('click', deleteSocialNetwork);
    });
});

// Προσθήκη νέου κοινωνικού δικτύου
function addSocialNetwork() {
    const select = document.getElementById('social-select');
    const selectedOption = select.options[select.selectedIndex];
    
    if (select.value) {
        const socialKey = select.value;
        const socialName = selectedOption.text;
        const socialIcon = selectedOption.getAttribute('data-icon');
        
        // Έλεγχος αν υπάρχει ήδη αυτό το κοινωνικό δίκτυο
        if (!document.querySelector(`[name="social_${socialKey}"]`)) {
            const container = document.getElementById('new-social-container');
            
            const socialDiv = document.createElement('div');
            socialDiv.className = 'social-link';
            socialDiv.dataset.social = socialKey;
            
            const icon = document.createElement('i');
            icon.className = socialIcon;
            
            const input = document.createElement('input');
            input.type = 'url';
            input.name = `social_${socialKey}`;
            input.className = 'form-control';
            input.placeholder = `URL προφίλ ${socialName}`;
            
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'social-delete';
            deleteButton.innerHTML = '<i class="fas fa-times"></i>';
            deleteButton.setAttribute('aria-label', 'Διαγραφή');
            deleteButton.addEventListener('click', deleteSocialNetwork);
            
            socialDiv.appendChild(icon);
            socialDiv.appendChild(input);
            socialDiv.appendChild(deleteButton);
            
            container.appendChild(socialDiv);
            
            // Αφαίρεση της επιλογής από το dropdown
            select.removeChild(selectedOption);
            select.selectedIndex = 0;
        }
    }
}

// Διαγραφή κοινωνικού δικτύου
function deleteSocialNetwork(event) {
    const button = event.target.closest('.social-delete');
    const socialLink = button.closest('.social-link');
    const socialKey = socialLink.dataset.social || socialLink.querySelector('input[name^="social_"]').name.replace('social_', '');
    const socialName = socialLink.querySelector('input').placeholder.replace('URL προφίλ ', '');
    
    // Προσθήκη του κοινωνικού δικτύου πίσω στο dropdown
    const select = document.getElementById('social-select');
    const option = document.createElement('option');
    option.value = socialKey;
    option.text = socialName;
    option.setAttribute('data-icon', socialLink.querySelector('i').className);
    select.appendChild(option);
    
    // Αφαίρεση του κοινωνικού δικτύου από τη φόρμα
    socialLink.remove();
}

// Προεπισκόπηση εικόνας λογότυπου
function previewLogo(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.school-logo');
            if (preview) {
                preview.src = e.target.result;
            } else {
                const newPreview = document.createElement('img');
                newPreview.src = e.target.result;
                newPreview.alt = 'Λογότυπο Σχολής';
                newPreview.className = 'school-logo';
                
                const container = document.querySelector('.logo-upload');
                const noLogoText = container.querySelector('p');
                if (noLogoText) {
                    container.replaceChild(newPreview, noLogoText);
                } else {
                    container.insertBefore(newPreview, container.firstChild);
                }
            }
        };
        reader.readAsDataURL(file);
    }
}

// Προσθήκη event listener για την προεπισκόπηση λογότυπου
document.addEventListener('DOMContentLoaded', function() {
    const logoInput = document.querySelector('input[name="logo"]');
    if (logoInput) {
        logoInput.addEventListener('change', previewLogo);
    }
});