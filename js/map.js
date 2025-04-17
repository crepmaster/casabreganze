// Fonction pour indiquer que ce script est chargé
console.log('map.js chargé');

// Fonction pour initialiser la carte Google Maps
function initMap() {
    // Les coordonnées de l'adresse (à remplacer par les coordonnées exactes)
    // Coordonnées approximatives pour Via Giovanni di Breganze 1, Milan
    const latitude = 45.4668;
    const longitude = 9.1905;
    
    // Configurer l'URL de l'iframe Google Maps
    const mapUrl = `https://www.google.com/maps/embed/v1/place?key=YOUR_API_KEY_HERE&q=Via+Giovanni+di+Breganze+1,+20152+Milan,+Italy&center=${latitude},${longitude}&zoom=15`;
    
    // Alternative sans clé API (moins de fonctionnalités, mais fonctionne sans clé)
    const mapUrlSimple = `https://maps.google.com/maps?q=Via+Giovanni+di+Breganze+1,+20152+Milan,+Italy&t=m&z=15&output=embed&iwloc=near`;
    
    // Mettre à jour l'iframe avec l'URL de la carte
    const mapFrame = document.getElementById('google-map');
    if (mapFrame) {
        // Utiliser l'URL simple qui ne nécessite pas de clé API
        mapFrame.src = mapUrlSimple;
    }
}

// Fonction pour calculer l'itinéraire depuis la position de l'utilisateur
// Cette fonction est préparée mais désactivée par défaut (nécessite une clé API et l'autorisation de l'utilisateur)
function calculateRoute() {
    // Vérifier si la géolocalisation est disponible
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const userLatitude = position.coords.latitude;
            const userLongitude = position.coords.longitude;
            
            // Coordonnées de l'appartement
            const apartmentLatitude = 45.4668;
            const apartmentLongitude = 9.1905;
            
            // Créer l'URL pour l'itinéraire
            const routeUrl = `https://www.google.com/maps/embed/v1/directions?key=YOUR_API_KEY_HERE&origin=${userLatitude},${userLongitude}&destination=Via+Giovanni+di+Breganze+1,+20152+Milan,+Italy&mode=driving`;
            
            // Mettre à jour l'iframe avec l'itinéraire
            const mapFrame = document.getElementById('google-map');
            if (mapFrame) {
                mapFrame.src = routeUrl;
            }
        });
    } else {
        console.error("La géolocalisation n'est pas prise en charge par ce navigateur.");
    }
}

// Note: Pour utiliser les fonctionnalités avancées de Google Maps comme les itinéraires,
// il faut obtenir une clé API Google Maps et remplacer 'YOUR_API_KEY_HERE' par la clé.
// https://developers.google.com/maps/documentation/embed/get-api-key