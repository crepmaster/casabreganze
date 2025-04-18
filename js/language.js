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
    
    // Mettre à jour le contenu HTML formaté (via la fonction du script principal)
    if (typeof updateContentByLanguage === 'function') {
        updateContentByLanguage(lang);
    }
    
    // Enregistrer la préférence de langue dans localStorage
    localStorage.setItem('preferredLanguage', lang);
    
    console.log(`Langue changée pour: ${lang}`);
}

// Fonction pour mettre à jour les éléments avec la langue sélectionnée
function updateElementsWithLanguage(lang) {
    // Mettre à jour tous les éléments ayant un attribut data-[lang]
    const elements = document.querySelectorAll(`[data-${lang}]`);
    
    elements.forEach(element => {
        const text = element.getAttribute(`data-${lang}`);
        if (text) {
            // Vérifier s'il s'agit d'un élément à contenu texte ou d'un attribut
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.placeholder = text;
            } else if (element.tagName === 'IMG') {
                element.alt = text;
            } else {
                // Si l'élément a un enfant de type texte, le mettre à jour
                if (element.childNodes.length > 0 && element.childNodes[0].nodeType === 3) {
                    element.childNodes[0].nodeValue = text;
                } else {
                    element.textContent = text;
                }
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

// Fonction pour déterminer la langue à utiliser au chargement
function detectInitialLanguage() {
    // Vérifier si une langue est enregistrée dans localStorage
    const savedLang = localStorage.getItem('preferredLanguage');
    if (savedLang) {
        return savedLang;
    }
    
    // Sinon, essayer de détecter la langue du navigateur
    const browserLang = navigator.language || navigator.userLanguage;
    const shortLang = browserLang.split('-')[0];
    
    // Vérifier si nous supportons cette langue
    const supportedLanguages = ['fr', 'en', 'it', 'es', 'pt', 'zh'];
    if (supportedLanguages.includes(shortLang)) {
        return shortLang;
    }
    
    // Par défaut, utiliser le français
    return 'fr';
}

// Initialiser la langue au chargement de la page
window.addEventListener('load', function() {
    const initialLang = detectInitialLanguage();
    setLanguage(initialLang);
});