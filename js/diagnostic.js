// Script de diagnostic pour identifier les problèmes de chargement
(function() {
    // Fonction pour ajouter un message de log à la page
    function addLog(message, isError = false) {
        // Créer ou récupérer la div de diagnostic
        let diagDiv = document.getElementById('diagnostic-log');
        
        if (!diagDiv) {
            diagDiv = document.createElement('div');
            diagDiv.id = 'diagnostic-log';
            diagDiv.style.position = 'fixed';
            diagDiv.style.bottom = '10px';
            diagDiv.style.right = '10px';
            diagDiv.style.maxWidth = '400px';
            diagDiv.style.maxHeight = '300px';
            diagDiv.style.overflow = 'auto';
            diagDiv.style.backgroundColor = 'rgba(0,0,0,0.8)';
            diagDiv.style.color = 'white';
            diagDiv.style.padding = '10px';
            diagDiv.style.borderRadius = '5px';
            diagDiv.style.zIndex = '9999';
            diagDiv.style.fontSize = '12px';
            diagDiv.style.fontFamily = 'monospace';
            
            // Ajouter un titre
            const title = document.createElement('h3');
            title.textContent = 'Diagnostic';
            title.style.margin = '0 0 10px 0';
            title.style.borderBottom = '1px solid white';
            diagDiv.appendChild(title);
            
            // Ajouter un bouton pour cacher/montrer
            const toggleBtn = document.createElement('button');
            toggleBtn.textContent = 'Cacher';
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.top = '5px';
            toggleBtn.style.right = '5px';
            toggleBtn.style.padding = '2px 5px';
            toggleBtn.style.fontSize = '10px';
            toggleBtn.onclick = function() {
                const logContent = document.getElementById('diagnostic-content');
                if (logContent.style.display === 'none') {
                    logContent.style.display = 'block';
                    toggleBtn.textContent = 'Cacher';
                } else {
                    logContent.style.display = 'none';
                    toggleBtn.textContent = 'Montrer';
                }
            };
            diagDiv.appendChild(toggleBtn);
            
            // Créer le conteneur de contenu
            const content = document.createElement('div');
            content.id = 'diagnostic-content';
            diagDiv.appendChild(content);
            
            document.body.appendChild(diagDiv);
        }
        
        // Ajouter le message
        const contentDiv = document.getElementById('diagnostic-content');
        const logEntry = document.createElement('div');
        logEntry.textContent = message;
        if (isError) {
            logEntry.style.color = '#ff6b6b';
        }
        contentDiv.appendChild(logEntry);
        
        // Scroller vers le bas
        contentDiv.scrollTop = contentDiv.scrollHeight;
        
        // Aussi envoyer au console
        if (isError) {
            console.error(message);
        } else {
            console.log(message);
        }
    }
    
    // Fonction pour vérifier la structure des répertoires
    function checkDirectoryStructure() {
        addLog('Vérification de la structure des répertoires...');
        
        // Vérifier les fichiers CSS
        const cssLinks = document.querySelectorAll('link[rel="stylesheet"]');
        cssLinks.forEach(link => {
            const xhr = new XMLHttpRequest();
            xhr.open('HEAD', link.href, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        addLog(`✓ CSS trouvé: ${link.href}`);
                    } else {
                        addLog(`✗ CSS non trouvé: ${link.href}`, true);
                    }
                }
            };
            xhr.send();
        });
        
        // Vérifier les scripts JS
        const scripts = document.querySelectorAll('script[src]');
        scripts.forEach(script => {
            const xhr = new XMLHttpRequest();
            xhr.open('HEAD', script.src, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        addLog(`✓ Script trouvé: ${script.src}`);
                    } else {
                        addLog(`✗ Script non trouvé: ${script.src}`, true);
                    }
                }
            };
            xhr.send();
        });
        
        // Vérifier les dossiers d'images
        const imageDirs = ['img/building', 'img/bedroom', 'img/bathroom', 'img/living', 'img/kitchen'];
        imageDirs.forEach(dir => {
            const testImagePath = `${dir}/test.jpg`;
            const img = new Image();
            img.onload = function() {
                addLog(`✓ Dossier d'images accessible: ${dir}`);
            };
            img.onerror = function() {
                addLog(`? Dossier d'images inaccessible ou vide: ${dir}`, false);
            };
            img.src = testImagePath;
        });
    }
    
    // Fonction pour vérifier la disponibilité des fonctions JavaScript
    function checkJavaScriptFunctions() {
        addLog('Vérification des fonctions JavaScript...');
        
        const functions = [
            'initGallery', 
            'setLanguage', 
            'initMap', 
            'createImageModal', 
            'initBookingButtons'
        ];
        
        functions.forEach(func => {
            if (typeof window[func] === 'function') {
                addLog(`✓ Fonction disponible: ${func}`);
            } else {
                addLog(`✗ Fonction manquante: ${func}`, true);
            }
        });
    }
    
    // Fonction pour vérifier la structure DOM
    function checkDOMStructure() {
        addLog('Vérification de la structure DOM...');
        
        const elements = [
            { selector: '.language-selector', name: 'Sélecteur de langue' },
            { selector: 'header .main-title', name: 'Titre principal' },
            { selector: '.cta-buttons', name: 'Boutons d\'appel à l\'action' },
            { selector: '.gallery-tabs', name: 'Onglets de galerie' },
            { selector: '.gallery-tab', name: 'Conteneurs de galerie' },
            { selector: '.map-container', name: 'Conteneur de carte' },
            { selector: '.poi-section', name: 'Section Points d\'intérêt' }
        ];
        
        elements.forEach(el => {
            const found = document.querySelector(el.selector);
            if (found) {
                addLog(`✓ Élément DOM trouvé: ${el.name}`);
            } else {
                addLog(`✗ Élément DOM manquant: ${el.name}`, true);
            }
        });
    }
    
    // Exécuter le diagnostic quand le DOM est chargé
    document.addEventListener('DOMContentLoaded', function() {
        addLog('Diagnostic démarré');
        addLog(`URL: ${window.location.href}`);
        addLog(`User Agent: ${navigator.userAgent}`);
        
        // Vérifier la structure après un court délai
        setTimeout(function() {
            checkDirectoryStructure();
            checkDOMStructure();
            checkJavaScriptFunctions();
            
            addLog('Diagnostic terminé. Consultez la console pour plus de détails.');
        }, 1000);
    });
})();