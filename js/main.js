// Script principal pour l'initialisation et la configuration
console.log('main.js chargé');

// Définir les fonctions AVANT l'événement DOMContentLoaded
// pour qu'elles soient disponibles globalement

// Fonction pour initialiser les boutons de réservation
function initBookingButtons() {
    console.log('Initialisation des boutons de réservation');
    
    // URL Airbnb fournie par le client
    const airbnbUrl = "https://www.airbnb.fr/rooms/1370963027643363967?adults=2&check_in=2025-04-10&check_out=2025-04-13&search_mode=regular_search&source_impression_id=p3_1741384692_P3X4CMiYSf8hsB6L&previous_page_section_name=1000&federated_search_id=053941b5-c6d5-4ab2-b578-e4863e4249e7";
    
    // URL Booking mise à jour
    const bookingUrl = "https://www.booking.com/Share-C54iPOs";
    
    // Configurer les liens
    const airbnbLink = document.getElementById('airbnb-link');
    const bookingLink = document.getElementById('booking-link');
    
    if (airbnbLink) {
        airbnbLink.href = airbnbUrl;
        console.log('URL Airbnb configurée');
    }
    
    if (bookingLink) {
        bookingLink.href = bookingUrl;
        console.log('URL Booking configurée');
    }
    
    // Améliorer les boutons en ajoutant des icônes
    if (airbnbLink) {
        const icon = document.createElement('i');
        icon.className = 'fab fa-airbnb';
        icon.style.marginRight = '8px';
        
        // Conserver le texte existant
        const text = airbnbLink.textContent;
        
        // Vider et reconstruire le contenu
        airbnbLink.innerHTML = '';
        airbnbLink.appendChild(icon);
        airbnbLink.appendChild(document.createTextNode(text));
    }
    
    if (bookingLink) {
        const icon = document.createElement('i');
        icon.className = 'fas fa-hotel';
        icon.style.marginRight = '8px';
        
        // Conserver le texte existant
        const text = bookingLink.textContent;
        
        // Vider et reconstruire le contenu
        bookingLink.innerHTML = '';
        bookingLink.appendChild(icon);
        bookingLink.appendChild(document.createTextNode(text));
    }
}

// Fonction pour créer le modal pour les images
function createImageModal() {
    console.log('Création du modal pour les images');
    // Vérifier si le modal existe déjà
    if (document.querySelector('.modal')) {
        console.log('Modal déjà existant');
        return;
    }
    
    // Créer l'élément modal
    const modal = document.createElement('div');
    modal.classList.add('modal');
    
    // Créer le contenu du modal
    const modalContent = document.createElement('img');
    modalContent.classList.add('modal-content');
    
    // Créer le bouton de fermeture
    const closeButton = document.createElement('span');
    closeButton.classList.add('modal-close');
    closeButton.innerHTML = '<i class="fas fa-times"></i>';
    closeButton.addEventListener('click', function() {
        modal.classList.remove('active');
    });
    
    // Assembler les éléments
    modal.appendChild(modalContent);
    modal.appendChild(closeButton);
    document.body.appendChild(modal);
    
    // Fermer le modal en cliquant n'importe où
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.remove('active');
        }
    });
    
    // Fermer le modal avec la touche Echap
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            modal.classList.remove('active');
        }
    });
    
    console.log('Modal créé avec succès');
}

// Script principal pour l'initialisation et la configuration
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - script principal exécuté');
    
    try {
        // Initialiser les URLs des boutons de réservation
        initBookingButtons();
        
        // Initialiser la langue par défaut
        if (typeof setLanguage === 'function') {
            setLanguage('fr');
        } else {
            console.error('Fonction setLanguage non disponible');
        }
        
        // Initialiser la carte Google Maps
        if (typeof initMap === 'function') {
            initMap();
        } else {
            console.error('Fonction initMap non disponible');
        }
        
        // Créer le modal pour les images (utilisé pour l'agrandissement depuis la galerie)
        createImageModal();
        
        // Ajouter des animations d'apparition pour les sections
        animateSections();
        
    } catch (error) {
        console.error('Erreur lors de l\'initialisation:', error);
    }
});

// Fonction pour animer l'apparition des sections au scroll
function animateSections() {
    const sections = document.querySelectorAll('section');
    
    // Fonction pour vérifier si un élément est visible dans la fenêtre
    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top <= (window.innerHeight || document.documentElement.clientHeight) * 0.8
        );
    }
    
    // Fonction pour gérer l'animation au scroll
    function handleScroll() {
        sections.forEach(section => {
            if (isInViewport(section) && !section.classList.contains('animated')) {
                section.classList.add('animated');
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }
        });
    }
    
    // Configurer le style initial pour les sections
    sections.forEach(section => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(30px)';
        section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        
        // Animer immédiatement les sections déjà visibles
        if (isInViewport(section)) {
            setTimeout(() => {
                section.classList.add('animated');
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, 300);
        }
    });
    
    // Ajouter l'événement de scroll
    window.addEventListener('scroll', handleScroll);
    
    // Déclencher une fois au chargement
    handleScroll();
}

// Fonction pour mettre à jour la position de la carte si l'URL contient un hash #map
function checkHashForMap() {
    if (window.location.hash === '#map') {
        const mapSection = document.querySelector('.location-section');
        if (mapSection) {
            setTimeout(() => {
                mapSection.scrollIntoView({ behavior: 'smooth' });
            }, 1000);
        }
    }
}

// Vérifier le hash au chargement
window.addEventListener('load', checkHashForMap);