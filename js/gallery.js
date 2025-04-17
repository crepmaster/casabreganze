// Variables globales pour le suivi des images et la navigation
let currentImageIndex = 0;
let galleryImages = [];

// Fonction pour indiquer que ce script est chargé
console.log('gallery.js chargé');

// Configuration des galeries d'images
function initGallery() {
    console.log('Initialisation de la galerie...');
    
    // Configuration des onglets de la galerie
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
    
    // Définir les images spécifiques pour chaque galerie
    const galleries = {
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
            // Retiré: 'tabeamanger.wbep',
            'tableamangerdeface.jpeg',
            'tableamangervuecuisne.jpeg'
        ],
        'kitchen': [
            'cuisine vu salon.jpeg',
            'cuisinewelcomekit.jpeg'
        ]
    };
    
    // Vider les galeries avant d'ajouter des images
    galleryTabs.forEach(tab => {
        while (tab.firstChild) {
            tab.removeChild(tab.firstChild);
        }
    });
    
    // Générer les galeries avec les images du répertoire img
    for (const [galleryName, imageFiles] of Object.entries(galleries)) {
        const galleryElement = document.getElementById(`${galleryName}-gallery`);
        
        if (!galleryElement) {
            console.error(`Élément de galerie non trouvé: ${galleryName}-gallery`);
            continue;
        }
        
        console.log(`Ajout de ${imageFiles.length} images à la galerie ${galleryName}`);
        
        imageFiles.forEach((imageFile, index) => {
            // Créer l'élément de la galerie
            const galleryItem = document.createElement('div');
            galleryItem.classList.add('gallery-item');
            
            // Créer l'élément image avec le chemin vers le fichier
            const img = document.createElement('img');
            
            // Construire le chemin vers l'image
            const imagePath = `img/${galleryName}/${imageFile}`;
            console.log(`Chargement de l'image: ${imagePath}`);
            
            // Définir la source de l'image
            img.src = imagePath;
            
            // Gérer les erreurs de chargement
            img.onerror = function() {
                console.error(`Image non trouvée: ${imagePath}`);
                this.src = `https://via.placeholder.com/800x600?text=${galleryName}`;
                this.alt = `Image ${galleryName} non disponible`;
            };
            
            // Définir le texte alternatif basé sur le nom du fichier
            img.alt = getImageAltText(galleryName, imageFile);
            
            // Activer le chargement paresseux pour les performances
            img.loading = 'lazy';
            
            // Ajouter la fonctionnalité d'agrandissement
            img.addEventListener('click', function() {
                openImageModal(this.src);
            });
            
            // Ajouter l'image à l'élément de la galerie
            galleryItem.appendChild(img);
            galleryElement.appendChild(galleryItem);
            
            console.log(`Image ajoutée: ${imageFile}`);
        });
    }
    
    // Vérifier si les galeries contiennent des images
    galleryTabs.forEach(tab => {
        const galleryName = tab.id.replace('-gallery', '');
        const itemCount = tab.querySelectorAll('.gallery-item').length;
        console.log(`Galerie ${galleryName}: ${itemCount} images ajoutées`);
    });
    
    // Créer les boutons de navigation pour la galerie
    addGalleryNavigation();
    
    // S'assurer que le premier onglet est actif
    if (tabButtons.length > 0 && galleryTabs.length > 0) {
        tabButtons[0].classList.add('active');
        galleryTabs[0].classList.add('active');
    }
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

// Fonction pour ouvrir le modal avec l'image agrandie
function openImageModal(imageSrc) {
    const modal = document.querySelector('.modal');
    const modalImg = document.querySelector('.modal-content');
    
    if (modal && modalImg) {
        // Mettre à jour l'image dans le modal
        modalImg.src = imageSrc;
        modal.classList.add('active');
        
        // Mettre à jour les variables globales pour la navigation
        const activeGallery = document.querySelector('.gallery-tab.active');
        if (activeGallery) {
            galleryImages = Array.from(activeGallery.querySelectorAll('.gallery-item img')).map(img => img.src);
            currentImageIndex = galleryImages.indexOf(imageSrc);
        }
    }
}

// Fonction pour ajouter des boutons de navigation à la galerie
function addGalleryNavigation() {
    const galleries = document.querySelectorAll('.gallery-tab');
    
    galleries.forEach(gallery => {
        // Vérifier si la galerie a au moins 2 images
        const itemCount = gallery.querySelectorAll('.gallery-item').length;
        console.log(`Ajout de navigation pour la galerie ${gallery.id}: ${itemCount} images`);
        
        if (itemCount < 2) return;
        
        // Créer le bouton précédent
        const prevBtn = document.createElement('button');
        prevBtn.classList.add('gallery-nav', 'prev-btn');
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.setAttribute('aria-label', 'Image précédente');
        
        // Créer le bouton suivant
        const nextBtn = document.createElement('button');
        nextBtn.classList.add('gallery-nav', 'next-btn');
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.setAttribute('aria-label', 'Image suivante');
        
        // Ajouter les boutons à la galerie
        gallery.appendChild(prevBtn);
        gallery.appendChild(nextBtn);
        
        // Ajouter les gestionnaires d'événements
        prevBtn.addEventListener('click', () => navigateGallery(gallery, 'prev'));
        nextBtn.addEventListener('click', () => navigateGallery(gallery, 'next'));
    });
}

// Fonction pour naviguer dans la galerie
function navigateGallery(gallery, direction) {
    // Récupérer toutes les images de la galerie
    const items = gallery.querySelectorAll('.gallery-item');
    if (items.length < 2) return;
    
    // Trouver l'image actuellement visible (si le défilement est activé)
    let visibleIndex = 0;
    const galleryRect = gallery.getBoundingClientRect();
    
    for (let i = 0; i < items.length; i++) {
        const itemRect = items[i].getBoundingClientRect();
        // Vérifier si l'image est principalement visible dans la viewport
        if (itemRect.left >= galleryRect.left && 
            itemRect.right <= galleryRect.right && 
            itemRect.top >= galleryRect.top && 
            itemRect.bottom <= galleryRect.bottom) {
            visibleIndex = i;
            break;
        }
    }
    
    // Calculer l'index de la prochaine image
    let nextIndex;
    if (direction === 'next') {
        nextIndex = (visibleIndex + 1) % items.length;
    } else {
        nextIndex = (visibleIndex - 1 + items.length) % items.length;
    }
    
    // Faire défiler vers l'image
    items[nextIndex].scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'center'
    });
}

// Fonction pour précharger les images (optimisation des performances)
function preloadGalleryImages() {
    // Précharger les premières images de chaque galerie
    const galleries = {
        'building': 'vueimmeuble.jpeg',
        'bedroom': 'chambrevuearmoire.jpeg',
        'bathroom': 'salledebain.jpeg',
        'living': 'livingvuensemble.jpeg',
        'kitchen': 'cuisine vu salon.jpeg'
    };
    
    for (const [galleryName, imageFile] of Object.entries(galleries)) {
        const img = new Image();
        img.src = `img/${galleryName}/${imageFile}`;
        console.log(`Préchargement de l'image: img/${galleryName}/${imageFile}`);
    }
}

// Exécuter initGallery quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - initialisation de la galerie');
    
    // Initialiser la galerie
    setTimeout(function() {
        initGallery();
    }, 100);
});

// Appeler la fonction de préchargement après le chargement complet de la page
window.addEventListener('load', function() {
    console.log('Page complètement chargée');
    preloadGalleryImages();
});