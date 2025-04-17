// Script pour la gestion des sliders de galerie
console.log('gallery-slider.js chargé');

// Variables globales pour le suivi des images et la navigation
let currentSlideIndexes = {
    'building': 0,
    'bedroom': 0,
    'bathroom': 0,
    'living': 0,
    'kitchen': 0
};

// Définition des images pour chaque galerie
const galleryImages = {
    'building': [
        'vueimmeuble.jpeg',
        'hallimmeuble.jpeg',
        'entreimmeuble.jpeg'
    ],
    'bedroom': [
        'chambrevuearmoire.jpeg',
        'chambrevuehaut.jpeg',
        'chambrevufenetre.jpeg'
    ],
    'bathroom': [
        'salledebain.jpeg',
        'salledebainvueantree.jpeg'
    ],
    'living': [
        'livingvuensemble.jpeg',
        'living.jpeg',
        'livingvuefenetre.jpeg',
        'salleamangervuehaut.jpeg',
        'salleamangevueliving.jpeg',
        'tableamangerdeface.jpeg',
        'tableamangervuecuisne.jpeg'
    ],
    'kitchen': [
        'cuisine vu salon.jpeg',
        'cuisinewelcomekit.jpeg'
    ]
};

// Fonction d'initialisation principale
function initGallerySliders() {
    console.log('Initialisation des sliders de galerie...');
    
    // Configuration des onglets de la galerie
    setupGalleryTabs();
    
    // Création des sliders pour chaque galerie
    createAllSliders();
    
    // Précharger les premières images
    preloadFirstImages();
}

// Fonction pour configurer les onglets de galerie
function setupGalleryTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const galleryTabs = document.querySelectorAll('.gallery-tab');
    
    console.log('Nombre d\'onglets trouvés:', tabButtons.length);
    console.log('Nombre de conteneurs de galerie trouvés:', galleryTabs.length);
    
    // Gestionnaire d'événements pour les onglets
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            console.log('Clic sur l\'onglet:', button.getAttribute('data-tab'));
            
            // Retirer la classe active de tous les onglets
            tabButtons.forEach(btn => btn.classList.remove('active'));
            galleryTabs.forEach(tab => tab.classList.remove('active'));
            
            // Ajouter la classe active à l'onglet cliqué
            button.classList.add('active');
            const tabId = button.getAttribute('data-tab');
            const targetGallery = document.getElementById(`${tabId}-gallery`);
            
            if (targetGallery) {
                targetGallery.classList.add('active');
                console.log('Galerie activée:', tabId);
            } else {
                console.error('Galerie non trouvée:', tabId);
            }
        });
    });
}

// Fonction pour créer tous les sliders
function createAllSliders() {
    // Pour chaque type de galerie, créer un slider
    for (const [galleryName, imageFiles] of Object.entries(galleryImages)) {
        createSlider(galleryName, imageFiles);
    }
}

// Fonction pour créer un slider pour une galerie spécifique
function createSlider(galleryName, imageFiles) {
    console.log(`Création du slider pour ${galleryName} avec ${imageFiles.length} images`);
    
    const galleryElement = document.getElementById(`${galleryName}-gallery`);
    if (!galleryElement) {
        console.error(`Élément de galerie non trouvé: ${galleryName}-gallery`);
        return;
    }
    
    // Vider le conteneur
    galleryElement.innerHTML = '';
    
    // Créer la structure du slider
    const sliderContainer = document.createElement('div');
    sliderContainer.className = 'slider-container';
    
    // Ajouter le wrapper des slides
    const slidesWrapper = document.createElement('div');
    slidesWrapper.className = 'slides-wrapper';
    sliderContainer.appendChild(slidesWrapper);
    
    // Créer chaque slide
    imageFiles.forEach((imageFile, index) => {
        const slide = document.createElement('div');
        slide.className = 'slide';
        if (index === 0) slide.classList.add('active');
        
        const img = document.createElement('img');
        img.src = `img/${galleryName}/${imageFile}`;
        img.alt = getImageAltText(galleryName, imageFile);
        img.loading = 'lazy';
        
        // Gérer les erreurs de chargement
        img.onerror = function() {
            console.error(`Image non trouvée: ${img.src}`);
            this.src = `https://via.placeholder.com/800x600?text=${galleryName}`;
            this.alt = `Image ${galleryName} non disponible`;
        };
        
        slide.appendChild(img);
        slidesWrapper.appendChild(slide);
    });
    
    // Ajouter les boutons de navigation
    const prevButton = document.createElement('button');
    prevButton.className = 'slider-nav prev-btn';
    prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevButton.setAttribute('aria-label', 'Image précédente');
    
    const nextButton = document.createElement('button');
    nextButton.className = 'slider-nav next-btn';
    nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextButton.setAttribute('aria-label', 'Image suivante');
    
    // Ajouter les indicateurs de pagination
    const pagination = document.createElement('div');
    pagination.className = 'slider-pagination';
    
    imageFiles.forEach((_, index) => {
        const dot = document.createElement('span');
        dot.className = 'pagination-dot';
        if (index === 0) dot.classList.add('active');
        
        dot.addEventListener('click', () => {
            goToSlide(galleryName, index);
        });
        
        pagination.appendChild(dot);
    });
    
    // Compteur de slides
    const counter = document.createElement('div');
    counter.className = 'slider-counter';
    counter.textContent = `1/${imageFiles.length}`;
    
    // Associer les événements aux boutons
    prevButton.addEventListener('click', () => {
        navigateSlider(galleryName, 'prev');
    });
    
    nextButton.addEventListener('click', () => {
        navigateSlider(galleryName, 'next');
    });
    
    // Ajouter les éléments au conteneur
    sliderContainer.appendChild(prevButton);
    sliderContainer.appendChild(nextButton);
    sliderContainer.appendChild(pagination);
    sliderContainer.appendChild(counter);
    
    // Ajouter le conteneur du slider à la galerie
    galleryElement.appendChild(sliderContainer);
    
    // Ajouter la navigation par clavier quand ce slider est visible
    document.addEventListener('keydown', function(event) {
        if (!galleryElement.classList.contains('active')) return;
        
        if (event.key === 'ArrowLeft') {
            navigateSlider(galleryName, 'prev');
        } else if (event.key === 'ArrowRight') {
            navigateSlider(galleryName, 'next');
        }
    });
}

// Fonction pour aller à un slide spécifique
function goToSlide(galleryName, index) {
    const galleryElement = document.getElementById(`${galleryName}-gallery`);
    if (!galleryElement) return;
    
    const slides = galleryElement.querySelectorAll('.slide');
    const dots = galleryElement.querySelectorAll('.pagination-dot');
    const counter = galleryElement.querySelector('.slider-counter');
    
    if (slides.length === 0) return;
    
    // Mettre à jour l'index actuel
    currentSlideIndexes[galleryName] = index;
    
    // Mettre à jour les slides
    slides.forEach((slide, i) => {
        if (i === index) {
            slide.classList.add('active');
        } else {
            slide.classList.remove('active');
        }
    });
    
    // Mettre à jour les points de pagination
    dots.forEach((dot, i) => {
        if (i === index) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    });
    
    // Mettre à jour le compteur
    if (counter) {
        counter.textContent = `${index + 1}/${slides.length}`;
    }
}

// Fonction pour naviguer dans le slider (précédent/suivant)
function navigateSlider(galleryName, direction) {
    const galleryElement = document.getElementById(`${galleryName}-gallery`);
    if (!galleryElement) return;
    
    const slides = galleryElement.querySelectorAll('.slide');
    if (slides.length <= 1) return;
    
    let newIndex;
    const currentIndex = currentSlideIndexes[galleryName];
    
    if (direction === 'next') {
        newIndex = (currentIndex + 1) % slides.length;
    } else {
        newIndex = (currentIndex - 1 + slides.length) % slides.length;
    }
    
    goToSlide(galleryName, newIndex);
}

// Fonction pour obtenir un texte alternatif pour l'image (important pour le SEO)
function getImageAltText(galleryName, imageFile) {
    // Essayer de générer un texte alternatif à partir du nom de fichier
    try {
        // Supprimer l'extension
        let altText = imageFile.split('.')[0];
        
        // Remplacer les caractères spéciaux par des espaces
        altText = altText.replace(/[_-]/g, ' ');
        
        // Si le nom contient "vue", "vue", etc., essayer de le décomposer
        if (altText.includes('vue') || altText.includes('vu')) {
            return `${galleryName} - ${altText}`;
        }
        
        return altText;
    } catch (e) {
        // En cas d'erreur, utiliser un texte alternatif par défaut
        return `Casa Breganze - ${galleryName}`;
    }
}

// Fonction pour précharger les premières images
function preloadFirstImages() {
    for (const [galleryName, imageFiles] of Object.entries(galleryImages)) {
        if (imageFiles.length > 0) {
            const img = new Image();
            img.src = `img/${galleryName}/${imageFiles[0]}`;
            console.log(`Préchargement de l'image: ${img.src}`);
        }
    }
}

// Initialiser les sliders quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - initialisation des sliders');
    setTimeout(initGallerySliders, 100);
});