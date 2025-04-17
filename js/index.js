// Script de secours pour charger le contenu initial
document.addEventListener('DOMContentLoaded', function() {
    console.log('Chargeur de secours activé');
    
    // Fonctions minimalistes pour assurer le chargement de base
    
    // Version simplifiée de initGallery
    window.basicInitGallery = function() {
        console.log('Initialisation basique de la galerie');
        
        // Activer le premier onglet
        const firstTabBtn = document.querySelector('.tab-btn');
        if (firstTabBtn) {
            firstTabBtn.classList.add('active');
            const tabId = firstTabBtn.getAttribute('data-tab');
            const firstTab = document.getElementById(`${tabId}-gallery`);
            if (firstTab) {
                firstTab.classList.add('active');
            }
        }
        
        // Générer quelques images placeholder dans chaque galerie
        const galleryTabs = document.querySelectorAll('.gallery-tab');
        galleryTabs.forEach(tab => {
            // Ajouter 3 images placeholder pour chaque galerie
            for (let i = 1; i <= 3; i++) {
                const galleryItem = document.createElement('div');
                galleryItem.classList.add('gallery-item');
                
                const img = document.createElement('img');
                img.src = `https://via.placeholder.com/800x600?text=Image+${i}`;
                img.alt = `Image ${i}`;
                img.loading = 'lazy';
                
                galleryItem.appendChild(img);
                tab.appendChild(galleryItem);
            }
        });
    };
    
    // Version simplifiée de setLanguage
    window.basicSetLanguage = function(lang) {
        console.log('Initialisation basique de la langue:', lang);
        document.documentElement.lang = lang;
        
        // Mettre à jour l'apparence des boutons de langue
        const languageButtons = document.querySelectorAll('.language-selector button');
        languageButtons.forEach(button => {
            if (button.getAttribute('data-lang') === lang) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });
        
        // Mettre à jour les textes
        const elements = document.querySelectorAll(`[data-${lang}]`);
        elements.forEach(element => {
            const text = element.getAttribute(`data-${lang}`);
            if (text) {
                element.textContent = text;
            }
        });
    };
    
    // Vérifier si les fonctions principales sont disponibles
    setTimeout(function() {
        // Si après 2 secondes les fonctions principales ne sont pas chargées, utiliser les versions de secours
        if (typeof initGallery !== 'function') {
            console.log('Utilisation du chargeur de galerie de secours');
            window.basicInitGallery();
        }
        
        if (typeof setLanguage !== 'function') {
            console.log('Utilisation du chargeur de langue de secours');
            window.basicSetLanguage('fr');
        }
        
        // Initialiser les boutons de réservation
        const airbnbUrl = "https://www.airbnb.fr/rooms/1370963027643363967?adults=2&check_in=2025-04-10&check_out=2025-04-13&search_mode=regular_search&source_impression_id=p3_1741384692_P3X4CMiYSf8hsB6L&previous_page_section_name=1000&federated_search_id=053941b5-c6d5-4ab2-b578-e4863e4249e7";
        const bookingUrl = "https://www.booking.com/hotel/it/apartment-milan-via-giovanni-di-breganze.html";
        
        const airbnbLink = document.getElementById('airbnb-link');
        const bookingLink = document.getElementById('booking-link');
        
        if (airbnbLink) airbnbLink.href = airbnbUrl;
        if (bookingLink) bookingLink.href = bookingUrl;
    }, 2000);
});