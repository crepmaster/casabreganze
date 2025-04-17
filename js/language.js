// Fonction pour indiquer que ce script est chargé
console.log('language.js chargé');

// Gestion du changement de langue sur la page
document.addEventListener('DOMContentLoaded', function() {
    // Sélecteur de langue
    const languageButtons = document.querySelectorAll('.language-selector button');
    
    // Ajouter les événements de clic sur les boutons de langue
    languageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const lang = this.getAttribute('data-lang');
            setLanguage(lang);
        });
    });
});

// Fonction pour changer la langue de la page
function setLanguage(lang) {
    // Mettre à jour la langue du document HTML
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
    
    // Mettre à jour tous les éléments avec attributs de langue
    updateElementsWithLanguage(lang);
}

// Fonction pour mettre à jour les éléments avec la langue sélectionnée
function updateElementsWithLanguage(lang) {
    // Mettre à jour tous les éléments ayant un attribut data-[lang]
    const elements = document.querySelectorAll(`[data-${lang}]`);
    
    elements.forEach(element => {
        const text = element.getAttribute(`data-${lang}`);
        if (text) {
            // Si l'élément a un enfant de type texte, le mettre à jour
            if (element.childNodes.length > 0 && element.childNodes[0].nodeType === 3) {
                element.childNodes[0].nodeValue = text;
            } else {
                element.textContent = text;
            }
        }
    });
    
    // Mettre à jour les attributs alt des images si nécessaire
    updateImageAltTexts(lang);
}

// Fonction pour mettre à jour les attributs alt des images
function updateImageAltTexts(lang) {
    // Cette fonction peut être complétée pour mettre à jour les textes alternatifs
    // des images selon la langue sélectionnée, si nécessaire
    
    // Pour l'instant, nous utilisons un système plus simple avec des attributs data-
    // mais cette fonction pourrait être étendue pour des besoins plus complexes
}