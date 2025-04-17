// Script de test pour s'assurer que les galeries sont correctement initialisées
(function() {
    console.log('Script de test des galeries chargé');
    
    // Fonction pour vérifier l'état des galeries
    function checkGalleries() {
        console.log('Vérification des galeries...');
        
        // Vérifier les conteneurs de galerie
        const galleryTabs = document.querySelectorAll('.gallery-tab');
        console.log(`Nombre de conteneurs de galerie trouvés: ${galleryTabs.length}`);
        
        if (galleryTabs.length === 0) {
            console.error('ERREUR: Aucun conteneur de galerie trouvé!');
            return;
        }
        
        // Vérifier si les galeries contiennent des images
        let totalImages = 0;
        galleryTabs.forEach(tab => {
            const galleryName = tab.id.replace('-gallery', '');
            const items = tab.querySelectorAll('.gallery-item');
            const images = tab.querySelectorAll('.gallery-item img');
            
            console.log(`Galerie "${galleryName}": ${items.length} éléments, ${images.length} images`);
            totalImages += images.length;
            
            if (items.length === 0) {
                // Si la galerie est vide, essayons de la remplir manuellement
                console.warn(`La galerie "${galleryName}" est vide, tentative de remplissage manuel...`);
                fillGalleryManually(galleryName, tab);
            }
        });
        
        console.log(`Total des images dans toutes les galeries: ${totalImages}`);
        
        // Vérifier l'onglet actif
        const activeTab = document.querySelector('.gallery-tab.active');
        if (!activeTab) {
            console.warn('Aucun onglet de galerie n\'est actif, activation du premier onglet...');
            const firstTab = document.querySelector('.gallery-tab');
            if (firstTab) {
                firstTab.classList.add('active');
                
                // Activer aussi le bouton correspondant
                const tabId = firstTab.id.replace('-gallery', '');
                const tabBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
                if (tabBtn) {
                    tabBtn.classList.add('active');
                }
            }
        }
    }
    
    // Fonction pour remplir manuellement une galerie avec des images
    function fillGalleryManually(galleryName, container) {
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
                'tableamangerdeface.jpeg',
                'tableamangervuecuisne.jpeg'
            ],
            'kitchen': [
                'cuisine vu salon.jpeg',
                'cuisinewelcomekit.jpeg'
            ]
        };
        
        if (!galleries[galleryName]) {
            console.error(`Aucune définition d'images pour la galerie "${galleryName}"`);
            return;
        }
        
        const images = galleries[galleryName];
        console.log(`Ajout manuel de ${images.length} images à la galerie "${galleryName}"...`);
        
        images.forEach(imageFile => {
            // Créer l'élément de la galerie
            const galleryItem = document.createElement('div');
            galleryItem.classList.add('gallery-item');
            
            // Créer l'élément image
            const img = document.createElement('img');
            img.src = `img/${galleryName}/${imageFile}`;
            img.alt = `${galleryName} - ${imageFile.split('.')[0]}`;
            img.loading = 'lazy';
            
            // Ajouter l'image à l'élément de la galerie
            galleryItem.appendChild(img);
            container.appendChild(galleryItem);
            
            console.log(`Image ajoutée manuellement: ${imageFile}`);
        });
    }
    
    // Exécuter la vérification après un court délai pour laisser le temps au DOM de se charger
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM chargé, vérification des galeries...');
        
        // Vérifier après 500ms
        setTimeout(checkGalleries, 500);
        
        // Vérifier à nouveau après 2 secondes (au cas où le chargement serait lent)
        setTimeout(checkGalleries, 2000);
    });
    
    // Ajouter un bouton pour forcer le rechargement des galeries
    setTimeout(function() {
        const button = document.createElement('button');
        button.textContent = '🔄 Recharger les galeries';
        button.style.position = 'fixed';
        button.style.bottom = '10px';
        button.style.left = '10px';
        button.style.padding = '10px';
        button.style.backgroundColor = '#2a6aa8';
        button.style.color = 'white';
        button.style.border = 'none';
        button.style.borderRadius = '5px';
        button.style.cursor = 'pointer';
        button.style.zIndex = '999';
        
        button.addEventListener('click', function() {
            console.log('Rechargement forcé des galeries...');
            if (typeof initGallery === 'function') {
                initGallery();
            } else {
                console.error('La fonction initGallery n\'est pas disponible');
                // Essayer de charger gallery.js
                const script = document.createElement('script');
                script.src = 'js/gallery.js';
                document.head.appendChild(script);
                
                // Réessayer après le chargement
                script.onload = function() {
                    if (typeof initGallery === 'function') {
                        initGallery();
                    }
                };
            }
            checkGalleries();
        });
        
        document.body.appendChild(button);
    }, 1000);
})();