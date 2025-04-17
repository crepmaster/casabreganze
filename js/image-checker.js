// Script pour vérifier l'accessibilité des images
document.addEventListener('DOMContentLoaded', function() {
    console.log('Vérification des images...');
    
    // Liste des images à vérifier
    const imagesToCheck = {
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
            'tabeamanger.wbep',
            'tableamangerdeface.jpeg',
            'tableamangervuecuisne.jpeg'
        ],
        'kitchen': [
            'cuisine vu salon.jpeg',
            'cuisinewelcomekit.jpeg'
        ]
    };
    
    // Créer un div pour afficher les résultats
    const resultDiv = document.createElement('div');
    resultDiv.style.position = 'fixed';
    resultDiv.style.bottom = '10px';
    resultDiv.style.left = '10px';
    resultDiv.style.backgroundColor = 'rgba(0,0,0,0.7)';
    resultDiv.style.color = 'white';
    resultDiv.style.padding = '10px';
    resultDiv.style.borderRadius = '5px';
    resultDiv.style.zIndex = '9999';
    resultDiv.style.fontSize = '12px';
    resultDiv.style.maxHeight = '300px';
    resultDiv.style.overflow = 'auto';
    resultDiv.style.maxWidth = '500px';
    resultDiv.innerHTML = '<h3>Vérification des images</h3>';
    document.body.appendChild(resultDiv);
    
    // Compteurs
    let totalImages = 0;
    let loadedImages = 0;
    let failedImages = 0;
    
    // Vérifier chaque image
    for (const [folder, files] of Object.entries(imagesToCheck)) {
        files.forEach(file => {
            totalImages++;
            const img = new Image();
            const path = `img/${folder}/${file}`;
            
            img.onload = function() {
                loadedImages++;
                resultDiv.innerHTML += `<p style="color:#5cb85c;">✓ Image trouvée: ${path}</p>`;
                updateSummary();
            };
            
            img.onerror = function() {
                failedImages++;
                resultDiv.innerHTML += `<p style="color:#d9534f;">✗ Image non trouvée: ${path}</p>`;
                updateSummary();
            };
            
            img.src = path;
        });
    }
    
    function updateSummary() {
        // Mettre à jour le résumé
        const summaryElement = document.getElementById('image-summary');
        if (summaryElement) {
            summaryElement.innerHTML = `Trouvées: ${loadedImages}/${totalImages} | Manquantes: ${failedImages}/${totalImages}`;
        } else {
            const summary = document.createElement('div');
            summary.id = 'image-summary';
            summary.style.marginTop = '10px';
            summary.style.borderTop = '1px solid white';
            summary.style.paddingTop = '5px';
            summary.style.fontWeight = 'bold';
            summary.innerHTML = `Trouvées: ${loadedImages}/${totalImages} | Manquantes: ${failedImages}/${totalImages}`;
            resultDiv.prepend(summary);
        }
        
        // Si toutes les images ont été vérifiées
        if (loadedImages + failedImages === totalImages) {
            if (failedImages > 0) {
                // Ajouter des suggestions
                resultDiv.innerHTML += `
                <div style="margin-top:10px; border-top:1px solid white; padding-top:5px;">
                    <h4>Suggestions :</h4>
                    <ul>
                        <li>Vérifiez que les noms de fichiers correspondent exactement (majuscules/minuscules)</li>
                        <li>Vérifiez que les extensions sont correctes (.jpeg, .jpg, .png)</li>
                        <li>Assurez-vous que les dossiers sont correctement nommés</li>
                        <li>Utilisez un outil comme <a href="https://imageoptim.com/" style="color:yellow;" target="_blank">ImageOptim</a> pour optimiser vos images</li>
                    </ul>
                </div>`;
            }
        }
    }
});