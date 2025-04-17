# Site Web - Appartement à Milan

Site web responsive et multilingue pour la promotion d'un appartement à louer à Milan, optimisé pour le référencement (SEO).

## Architecture du Projet

```
📁 Racine du projet
│
├─ 📄 index.html                # Page principale en HTML5
│
├─ 📁 css                       # Styles CSS
│  ├─ 📄 style.css              # Styles principaux
│  └─ 📄 responsive.css         # Styles responsives (adaptations mobile/tablette)
│
├─ 📁 js                        # Scripts JavaScript
│  ├─ 📄 main.js                # Script principal et initialisation
│  ├─ 📄 gallery.js             # Gestion des galeries d'images
│  ├─ 📄 language.js            # Gestion du multilingue (FR/EN/IT)
│  └─ 📄 map.js                 # Intégration de Google Maps
│
└─ 📁 img                       # Images et ressources visuelles
   ├─ 🖼️ favicon.ico            # Favicon du site
   ├─ 📁 building               # Photos de l'immeuble
   │  ├─ 🖼️ 1.jpg
   │  ├─ 🖼️ 2.jpg
   │  ├─ 🖼️ 3.jpg
   │  └─ 🖼️ 4.jpg
   │
   ├─ 📁 bedroom                # Photos de la chambre
   │  ├─ 🖼️ 1.jpg
   │  ├─ 🖼️ 2.jpg
   │  └─ 🖼️ ...
   │
   ├─ 📁 bathroom               # Photos de la salle de bain
   │  └─ 🖼️ ...
   │
   ├─ 📁 living                 # Photos du salon/living
   │  └─ 🖼️ ...
   │
   └─ 📁 kitchen                # Photos de la cuisine
      └─ 🖼️ ...
```

## Fonctionnalités

- **Design responsive** : Compatible avec tous les appareils (mobile, tablette, desktop)
- **Multilingue** : Français, Anglais et Italien
- **Galeries organisées par catégorie** :
  - Immeuble (building)
  - Chambre (bedroom)
  - Salle de bain (bathroom)
  - Salon (living)
  - Cuisine (kitchen)
- **Carte interactive** : Intégration Google Maps
- **Points d'intérêt** : Attractions, restaurants et transports à proximité
- **Optimisation SEO** : Balises meta, structure HTML sémantique, textes alt pour les images

## Instructions d'installation

1. Clonez ce dépôt ou téléchargez les fichiers
2. Organisez vos images dans les dossiers correspondants sous `/img`
   - Toutes les images doivent être nommées avec des nombres (1.jpg, 2.jpg, etc.)
   - Format recommandé : JPG ou WebP pour une meilleure performance
   - Dimensions recommandées : 1200x800px (ratio 3:2)
3. L'URL Airbnb est déjà configurée, mais vous pouvez mettre à jour l'URL Booking dans `js/main.js`
   ```javascript
   const bookingUrl = "https://www.booking.com/hotel/it/VOTRE_ID.html";
   ```
4. Pour activer la carte Google Maps avec toutes les fonctionnalités, obtenez une clé API Google Maps et mettez à jour `js/map.js`
   ```javascript
   const mapUrl = `https://www.google.com/maps/embed/v1/place?key=VOTRE_CLE_API_ICI&q=...`;
   ```

## Optimisations SEO

Le site est optimisé pour le référencement avec :
- Des balises title et meta description pertinentes
- Structure HTML5 sémantique
- Attributs alt sur toutes les images
- Structure hiérarchique des titres (H1, H2, H3)
- Contenu multilingue avec attributs lang
- Chargement optimisé des ressources (lazy loading)

## Personnalisation

- Couleurs : Modifiez les variables CSS dans `css/style.css`
- Textes : Modifiez les attributs data-fr, data-en et data-it dans `index.html`
- Points d'intérêt : Mettez à jour la section "poi-section" dans `index.html`

## Notes techniques

- Le site utilise Font Awesome pour les icônes
- Les images sont chargées de manière asynchrone pour améliorer les performances
- Le site est entièrement fonctionnel même sans JavaScript (dégradation élégante)