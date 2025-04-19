/**
 * Fichier principal JavaScript pour le site web de location d'appartement à Milan
 * 
 * Ce fichier est organisé en sections:
 * 1. Configuration initiale et fonctions de base
 * 2. Fonctions pour la gestion des contenus et des langues
 * 3. Fonctions pour les composants visuels et interactifs
 * 4. Initialisation au chargement de la page
 */

console.log('main.js chargé');

/******************************************************************************
 * SECTION 1: CONFIGURATION INITIALE ET FONCTIONS DE BASE
 ******************************************************************************/

/**
 * Initialise les boutons de réservation avec les liens Airbnb et Booking
 * Configure les icônes et les URLs pour tous les boutons de réservation
 */
function initBookingButtons() {
    console.log('Initialisation des boutons de réservation');
    
    // URL Airbnb fournie par le client
    const airbnbUrl = "https://www.airbnb.fr/rooms/1370963027643363967?adults=2&check_in=2025-04-10&check_out=2025-04-13&search_mode=regular_search&source_impression_id=p3_1741384692_P3X4CMiYSf8hsB6L&previous_page_section_name=1000&federated_search_id=053941b5-c6d5-4ab2-b578-e4863e4249e7";
    
    // URL Booking mise à jour
    const bookingUrl = "https://www.booking.com/Share-C54iPOs";
    
    // Configurer tous les liens (en haut, en bas, dans la section prix et dans la bannière flottante)
    const airbnbLinks = document.querySelectorAll('#airbnb-link-top, #airbnb-link-bottom, #airbnb-link-price, #airbnb-link-float');
    const bookingLinks = document.querySelectorAll('#booking-link-top, #booking-link-bottom, #booking-link-price, #booking-link-float');
    
    // Ajouter les icônes et les URLs pour les liens Airbnb
    airbnbLinks.forEach(link => {
        link.href = airbnbUrl;
        // Ajouter l'icône seulement si elle n'existe pas déjà et si ce n'est pas le lien flottant
        if (!link.querySelector('i') && link.id !== 'airbnb-link-float') {
            const icon = document.createElement('i');
            icon.className = 'fab fa-airbnb';
            link.prepend(icon);
        }
    });
    
    // Ajouter les icônes et les URLs pour les liens Booking
    bookingLinks.forEach(link => {
        link.href = bookingUrl;
        // Ajouter l'icône seulement si elle n'existe pas déjà et si ce n'est pas le lien flottant
        if (!link.querySelector('i') && link.id !== 'booking-link-float') {
            const icon = document.createElement('i');
            icon.className = 'fas fa-hotel';
            link.prepend(icon);
        }
    });
}

/**
 * Gère l'affichage et le masquage de la bannière flottante de prix
 * La bannière s'affiche lorsque l'utilisateur fait défiler la page
 */
function initFloatingPriceBanner() {
    const floatingPrice = document.getElementById('floating-price');
    if (floatingPrice) {
        window.addEventListener('scroll', function() {
            // Afficher la bannière lorsque l'utilisateur a défilé 500px
            if (window.scrollY > 500) {
                floatingPrice.classList.add('show');
            } else {
                floatingPrice.classList.remove('show');
            }
        });
    }
}

/**
 * Vérifie si l'URL contient un hash #map pour faire défiler jusqu'à la carte
 */
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

/**
 * Initialise la carte Google Maps avec l'adresse de l'appartement
 */
function initMap() {
    console.log('Initialisation de Google Maps');
    const mapFrame = document.getElementById('google-map');
    if (mapFrame) {
        mapFrame.src = `https://maps.google.com/maps?q=Via+Giovanni+di+Breganze+1,+20152+Milan,+Italy&t=m&z=15&output=embed&iwloc=near`;
    }
}

/**
 * Anime les sections de la page lors du défilement
 * Les sections apparaissent progressivement lorsqu'elles entrent dans la vue
 */
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
/******************************************************************************
 * SECTION 2: GESTION DU CONTENU ET DES LANGUES
 ******************************************************************************/

/**
 * Crée ou met à jour un élément HTML avec du contenu formaté
 * @param {string} elementId - L'identifiant de l'élément HTML à mettre à jour
 * @param {string} content - Le contenu HTML à insérer
 */
function createStyledContent(elementId, content) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content;
    }
}

/**
 * Met à jour les contenus textuels selon la langue sélectionnée
 * Personnalise les paragraphes de présentation avec des styles HTML
 * @param {string} lang - Code de la langue (fr, en, it, es, pt, zh)
 */
function updateContentByLanguage(lang) {
    // Français (par défaut)
    if (lang === 'fr') {
        createStyledContent('overview-paragraph-1', 
            '<p>Bienvenue dans ce <strong style="color:#e67e22">magnifique appartement milanais</strong>, idéalement situé au cœur de la ville. Niché dans un quartier tranquille mais bien connecté, cet espace de vie élégant offre une combinaison parfaite entre confort moderne et charme italien.</p>'
        );
        
        createStyledContent('overview-paragraph-2', 
            '<p>Conçu pour <strong style="color:#e67e22">2 personnes</strong>, notre appartement est parfait pour les couples en escapade romantique, les voyageurs d\'affaires ou les amis en citytrip. À seulement 800 mètres de la station de métro Bisceglie (ligne 1), vous pourrez facilement rejoindre le Duomo, les boutiques de luxe, le stade San Siro et même la célèbre Foire de Milan.</p>'
        );
        
        createStyledContent('overview-paragraph-3', 
            '<p><strong style="color:#e67e22">Ne cherchez plus</strong> et réservez dès maintenant pour découvrir Milan comme un véritable local, dans un cadre confortable et élégant qui deviendra votre chez-vous loin de chez vous.</p>'
        );
    }
    // Anglais
    else if (lang === 'en') {
        createStyledContent('overview-paragraph-1', 
            '<p>Welcome to this <strong style="color:#e67e22">magnificent Milanese apartment</strong>, ideally located in the heart of the city. Nestled in a quiet yet well-connected neighborhood, this elegant living space offers a perfect combination of modern comfort and Italian charm.</p>'
        );
        
        createStyledContent('overview-paragraph-2', 
            '<p>Designed for <strong style="color:#e67e22">2 people</strong>, our apartment is perfect for couples on a romantic getaway, business travelers, or friends on a city trip. Just 800 meters from the Bisceglie metro station (line 1), you can easily reach the Duomo, luxury shops, San Siro Stadium, and even the famous Milan Trade Fair.</p>'
        );
        
        createStyledContent('overview-paragraph-3', 
            '<p><strong style="color:#e67e22">Look no further</strong> and book now to discover Milan like a true local, in a comfortable and elegant setting that will become your home away from home.</p>'
        );
    }
    // Italien
    else if (lang === 'it') {
        createStyledContent('overview-paragraph-1', 
            '<p>Benvenuti in questo <strong style="color:#e67e22">magnifico appartamento milanese</strong>, situato idealmente nel cuore della città. Situato in un quartiere tranquillo ma ben collegato, questo elegante spazio abitativo offre una perfetta combinazione di comfort moderno e fascino italiano.</p>'
        );
        
        createStyledContent('overview-paragraph-2', 
            '<p>Progettato per <strong style="color:#e67e22">2 persone</strong>, il nostro appartamento è perfetto per coppie in fuga romantica, viaggiatori d\'affari o amici in gita in città. A soli 800 metri dalla stazione della metropolitana Bisceglie (linea 1), potrete raggiungere facilmente il Duomo, le boutique di lusso, lo Stadio San Siro e persino la famosa Fiera di Milano.</p>'
        );
        
        createStyledContent('overview-paragraph-3', 
            '<p><strong style="color:#e67e22">Non cercate oltre</strong> e prenotate ora per scoprire Milano come un vero locale, in un ambiente confortevole ed elegante che diventerà la vostra casa lontano da casa.</p>'
        );
    }
    // Espagnol
    else if (lang === 'es') {
        createStyledContent('overview-paragraph-1', 
            '<p>Bienvenido a este <strong style="color:#e67e22">magnífico apartamento milanés</strong>, ubicado idealmente en el corazón de la ciudad. Situado en un barrio tranquilo pero bien conectado, este elegante espacio ofrece una perfecta combinación de confort moderno y encanto italiano.</p>'
        );
        
        createStyledContent('overview-paragraph-2', 
            '<p>Diseñado para <strong style="color:#e67e22">2 personas</strong>, nuestro apartamento es perfecto para parejas en una escapada romántica, viajeros de negocios o amigos en una excursión urbana. A solo 800 metros de la estación de metro Bisceglie (línea 1), podrá llegar fácilmente al Duomo, tiendas de lujo, el Estadio San Siro e incluso la famosa Feria de Milán.</p>'
        );
        
        createStyledContent('overview-paragraph-3', 
            '<p><strong style="color:#e67e22">No busque más</strong> y reserve ahora para descubrir Milán como un verdadero local, en un entorno cómodo y elegante que se convertirá en su hogar lejos de casa.</p>'
        );
    }
    // Portugais
    else if (lang === 'pt') {
        createStyledContent('overview-paragraph-1', 
            '<p>Bem-vindo a este <strong style="color:#e67e22">magnífico apartamento milanês</strong>, idealmente localizado no coração da cidade. Situado num bairro tranquilo mas bem conectado, este elegante espaço habitacional oferece uma combinação perfeita de conforto moderno e charme italiano.</p>'
        );
        
        createStyledContent('overview-paragraph-2', 
            '<p>Projetado para <strong style="color:#e67e22">2 pessoas</strong>, o nosso apartamento é perfeito para casais em fuga romântica, viajantes de negócios ou amigos em viagem pela cidade. A apenas 800 metros da estação de metro Bisceglie (linha 1), pode facilmente chegar ao Duomo, às lojas de luxo, ao Estádio San Siro e até à famosa Feira de Milão.</p>'
        );
        
        createStyledContent('overview-paragraph-3', 
            '<p><strong style="color:#e67e22">Não procure mais</strong> e reserve agora para descobrir Milão como um verdadeiro local, num ambiente confortável e elegante que se tornará a sua casa longe de casa.</p>'
        );
    }
    // Chinois
    else if (lang === 'zh') {
        createStyledContent('overview-paragraph-1', 
            '<p>欢迎来到这间<strong style="color:#e67e22">精美的米兰公寓</strong>，理想地位于城市的中心。坐落在安静但交通便利的社区，这个优雅的生活空间提供现代舒适与意大利魅力的完美结合。</p>'
        );
        
        createStyledContent('overview-paragraph-2', 
            '<p>为<strong style="color:#e67e22">2人</strong>设计，我们的公寓非常适合浪漫度假的情侣、商务旅行者或城市旅行的朋友。距离Bisceglie地铁站（1号线）仅800米，您可以轻松抵达大教堂、奢侈品商店、圣西罗球场，甚至著名的米兰博览会。</p>'
        );
        
        createStyledContent('overview-paragraph-3', 
            '<p><strong style="color:#e67e22">不要再犹豫</strong>，立即预订，在舒适优雅的环境中像当地人一样探索米兰，这里将成为您在外的家。</p>'
        );
    }
}

/**
 * Initialise la gestion du changement de langue
 * Ajoute les écouteurs d'événements pour les boutons de langue
 */
function initLanguageSwitcher() {
    // Récupérer tous les boutons de langue
    const languageButtons = document.querySelectorAll('.language-selector button');
    
    // Ajouter un événement de clic à chaque bouton
    languageButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Récupérer le code de langue du bouton cliqué
            const lang = this.getAttribute('data-lang');
            
            // Mettre à jour l'apparence des boutons (active/inactive)
            languageButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Changer l'attribut lang du document
            document.documentElement.lang = lang;
            
            // Mettre à jour tous les éléments avec l'attribut data-lang correspondant
            document.querySelectorAll(`[data-${lang}]`).forEach(element => {
                // Récupérer le texte dans la langue appropriée
                const text = element.getAttribute(`data-${lang}`);
                if (text) {
                    // Appliquer le texte selon le type d'élément
                    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                        element.placeholder = text;
                    } else if (element.tagName === 'IMG') {
                        element.alt = text;
                    } else {
                        element.textContent = text;
                    }
                }
            });
            
            // Mettre à jour le contenu formaté avec HTML
            updateContentByLanguage(lang);
        });
    });
}

/**
 * Initialise le calendrier de disponibilité simulé
 * Crée un iframe avec un calendrier interactif
 */
function initCalendar() {
    const calendarContainer = document.getElementById('availability-calendar');
    if (calendarContainer) {
        // Créer un iframe pour simuler un calendrier
        const calendarFrame = document.createElement('iframe');
        calendarFrame.src = "about:blank";
        calendarFrame.width = "100%";
        calendarFrame.height = "100%";
        calendarFrame.frameBorder = "0";
        
        // Remplir l'iframe avec le HTML du calendrier
        calendarFrame.onload = function() {
            const doc = this.contentDocument || this.contentWindow.document;
            doc.open();
            doc.write(`
                <html>
                <head>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 0;
                            padding: 20px;
                            background-color: #f9f9f9;
                        }
                        .calendar-container {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 20px;
                            justify-content: center;
                        }
                        .month {
                            background-color: white;
                            border-radius: 10px;
                            padding: 15px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                            width: 280px;
                        }
                        .month-name {
                            text-align: center;
                            font-weight: bold;
                            margin-bottom: 10px;
                            color: #2a6aa8;
                            font-size: 1.2rem;
                        }
                        .weekdays {
                            display: grid;
                            grid-template-columns: repeat(7, 1fr);
                            text-align: center;
                            font-weight: bold;
                            margin-bottom: 10px;
                            font-size: 0.8rem;
                            color: #666;
                        }
                        .days {
                            display: grid;
                            grid-template-columns: repeat(7, 1fr);
                            gap: 5px;
                            text-align: center;
                        }
                        .day {
                            padding: 8px 0;
                            border-radius: 5px;
                            cursor: pointer;
                            font-size: 0.9rem;
                        }
                        .day.available {
                            background-color: #e0f7e0;
                            color: #2a6aa8;
                        }
                        .day.available:hover {
                            background-color: #c0e7c0;
                        }
                        .day.booked {
                            background-color: #ffe6e6;
                            color: #999;
                            text-decoration: line-through;
                            cursor: not-allowed;
                        }
                        .day.other-month {
                            color: #ddd;
                            background-color: transparent;
                            cursor: default;
                        }
                        .legend {
                            display: flex;
                            justify-content: center;
                            margin-top: 20px;
                            gap: 20px;
                        }
                        .legend-item {
                            display: flex;
                            align-items: center;
                            font-size: 0.85rem;
                        }
                        .legend-color {
                            width: 15px;
                            height: 15px;
                            border-radius: 3px;
                            margin-right: 5px;
                        }
                        .available-color {
                            background-color: #e0f7e0;
                        }
                        .booked-color {
                            background-color: #ffe6e6;
                        }
                    </style>
                </head>
                <body>
                    <div class="calendar-container">
                        <!-- Avril 2025 -->
                        <div class="month">
                            <div class="month-name">Avril 2025</div>
                            <div class="weekdays">
                                <div>L</div>
                                <div>M</div>
                                <div>M</div>
                                <div>J</div>
                                <div>V</div>
                                <div>S</div>
                                <div>D</div>
                            </div>
                            <div class="days">
                                <div class="day other-month">31</div>
                                <div class="day available">1</div>
                                <div class="day available">2</div>
                                <div class="day available">3</div>
                                <div class="day available">4</div>
                                <div class="day available">5</div>
                                <div class="day available">6</div>
                                <div class="day available">7</div>
                                <div class="day available">8</div>
                                <div class="day available">9</div>
                                <div class="day booked">10</div>
                                <div class="day booked">11</div>
                                <div class="day booked">12</div>
                                <div class="day booked">13</div>
                                <div class="day available">14</div>
                                <div class="day available">15</div>
                                <div class="day available">16</div>
                                <div class="day available">17</div>
                                <div class="day available">18</div>
                                <div class="day available">19</div>
                                <div class="day available">20</div>
                                <div class="day booked">21</div>
                                <div class="day booked">22</div>
                                <div class="day booked">23</div>
                                <div class="day booked">24</div>
                                <div class="day booked">25</div>
                                <div class="day available">26</div>
                                <div class="day available">27</div>
                                <div class="day available">28</div>
                                <div class="day available">29</div>
                                <div class="day available">30</div>
                                <div class="day other-month">1</div>
                                <div class="day other-month">2</div>
                                <div class="day other-month">3</div>
                                <div class="day other-month">4</div>
                            </div>
                        </div>
                        
                        <!-- Mai 2025 -->
                        <div class="month">
                            <div class="month-name">Mai 2025</div>
                            <div class="weekdays">
                                <div>L</div>
                                <div>M</div>
                                <div>M</div>
                                <div>J</div>
                                <div>V</div>
                                <div>S</div>
                                <div>D</div>
                            </div>
                            <div class="days">
                                <div class="day other-month">28</div>
                                <div class="day other-month">29</div>
                                <div class="day other-month">30</div>
                                <div class="day available">1</div>
                                <div class="day available">2</div>
                                <div class="day available">3</div>
                                <div class="day available">4</div>
                                <div class="day available">5</div>
                                <div class="day available">6</div>
                                <div class="day available">7</div>
                                <div class="day available">8</div>
                                <div class="day available">9</div>
                                <div class="day booked">10</div>
                                <div class="day booked">11</div>
                                <div class="day booked">12</div>
                                <div class="day booked">13</div>
                                <div class="day booked">14</div>
                                <div class="day booked">15</div>
                                <div class="day available">16</div>
                                <div class="day available">17</div>
                                <div class="day available">18</div>
                                <div class="day available">19</div>
                                <div class="day available">20</div>
                                <div class="day available">21</div>
                                <div class="day available">22</div>
                                <div class="day available">23</div>
                                <div class="day available">24</div>
                                <div class="day available">25</div>
                                <div class="day available">26</div>
                                <div class="day available">27</div>
                                <div class="day available">28</div>
                                <div class="day available">29</div>
                                <div class="day available">30</div>
                                <div class="day available">31</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color available-color"></div>
                            <span>Disponible</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color booked-color"></div>
                            <span>Réservé</span>
                        </div>
                    </div>
                </body>
                </html>
            `);
            doc.close();
        };
        
        // Ajouter l'iframe au conteneur
        calendarContainer.appendChild(calendarFrame);
    }
}
/******************************************************************************
 * SECTION 3: COMPOSANTS VISUELS ET INTERACTIFS
 ******************************************************************************/

/**
 * Crée le modal pour l'affichage en plein écran des images
 * Permet à l'utilisateur de cliquer sur les images pour les agrandir
 */
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
    
    // Créer le contenu du modal (image)
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
    
    // Fermer le modal en cliquant n'importe où sur l'arrière-plan
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

/**
 * Initialise les onglets de galerie et les sliders d'images
 * Gère le changement d'onglets et l'affichage des images
 */
function initGalleryTabs() {
    console.log('Initialisation des onglets de galerie');
    
    const tabButtons = document.querySelectorAll('.tab-btn');
    const galleryTabs = document.querySelectorAll('.gallery-tab');
    
    // S'assurer qu'un seul onglet est actif au démarrage
    galleryTabs.forEach((tab, index) => {
        if (index === 0) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });
    
    // S'assurer qu'un seul bouton est actif au démarrage
    tabButtons.forEach((btn, index) => {
        if (index === 0) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Ajouter des événements de clic aux boutons d'onglets
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Retirer la classe active de tous les boutons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // Ajouter la classe active au bouton cliqué
            button.classList.add('active');
            
            // Récupérer l'onglet à afficher
            const tab = button.getAttribute('data-tab');
            
            // Masquer tous les onglets
            galleryTabs.forEach(tab => tab.classList.remove('active'));
            
            // Afficher l'onglet correspondant
            document.getElementById(`${tab}-gallery`).classList.add('active');
        });
    });
    
    // Initialiser les images de la galerie pour qu'elles ouvrent le modal
    const galleryImages = document.querySelectorAll('.gallery-item img');
    galleryImages.forEach(img => {
        img.addEventListener('click', function() {
            const modal = document.querySelector('.modal');
            const modalContent = document.querySelector('.modal-content');
            
            if (modal && modalContent) {
                modalContent.src = this.src;
                modal.classList.add('active');
            }
        });
    });
    
    // Initialiser les sliders après avoir configuré les onglets
    initGallerySliders();
}

/**
 * Initialise les sliders de galerie d'images
 * Crée les boutons de navigation et les indicateurs pour chaque galerie
 */
function initGallerySliders() {
    console.log('Initialisation des sliders de galerie');
    
    const galleryTabs = document.querySelectorAll('.gallery-tab');
    
    galleryTabs.forEach(tab => {
        const galleryItems = tab.querySelectorAll('.gallery-item');
        if (galleryItems.length === 0) return;
        
        // Définir la première image comme active
        let currentIndex = 0;
        galleryItems[0].classList.add('active');
        
        // Créer les boutons de navigation
        const prevBtn = document.createElement('button');
        prevBtn.className = 'gallery-nav prev-btn';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        
        const nextBtn = document.createElement('button');
        nextBtn.className = 'gallery-nav next-btn';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        
        // Créer les indicateurs
        const indicators = document.createElement('div');
        indicators.className = 'slider-indicators';
        
        for (let i = 0; i < galleryItems.length; i++) {
            const indicator = document.createElement('span');
            indicator.className = i === 0 ? 'indicator active' : 'indicator';
            indicator.dataset.index = i;
            indicators.appendChild(indicator);
            
            // Événement pour cliquer sur un indicateur
            indicator.addEventListener('click', () => {
                showSlide(i);
            });
        }
        
        // Ajouter les éléments au DOM
        tab.appendChild(prevBtn);
        tab.appendChild(nextBtn);
        tab.appendChild(indicators);
        
        // Fonction pour afficher une diapositive spécifique
        function showSlide(index) {
            if (index < 0) index = galleryItems.length - 1;
            if (index >= galleryItems.length) index = 0;
            
            // Cacher toutes les images et indicateurs actifs
            galleryItems.forEach(item => item.classList.remove('active'));
            indicators.querySelectorAll('.indicator').forEach(ind => ind.classList.remove('active'));
            
            // Afficher l'image active et l'indicateur actif
            galleryItems[index].classList.add('active');
            indicators.querySelectorAll('.indicator')[index].classList.add('active');
            
            currentIndex = index;
        }
        
        // Événements pour les boutons de navigation
        prevBtn.addEventListener('click', () => {
            showSlide(currentIndex - 1);
        });
        
        nextBtn.addEventListener('click', () => {
            showSlide(currentIndex + 1);
        });
        
        // Navigation avec les flèches du clavier
        document.addEventListener('keydown', (e) => {
            if (tab.classList.contains('active')) {
                if (e.key === 'ArrowLeft') {
                    showSlide(currentIndex - 1);
                } else if (e.key === 'ArrowRight') {
                    showSlide(currentIndex + 1);
                }
            }
        });
    });
}
/******************************************************************************
 * SECTION 3: COMPOSANTS VISUELS ET INTERACTIFS (SUITE)
 ******************************************************************************/

/**
 * Crée le modal pour l'affichage en plein écran des images
 * Permet à l'utilisateur de cliquer sur les images pour les agrandir
 */
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
    
    // Créer le contenu du modal (image)
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
    
    // Fermer le modal en cliquant n'importe où sur l'arrière-plan
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

/**
 * Initialise les onglets de galerie et les sliders d'images
 * Gère le changement d'onglets et l'affichage des images
 */
function initGalleryTabs() {
    console.log('Initialisation des onglets de galerie');
    
    const tabButtons = document.querySelectorAll('.tab-btn');
    const galleryTabs = document.querySelectorAll('.gallery-tab');
    
    // S'assurer qu'un seul onglet est actif au démarrage
    galleryTabs.forEach((tab, index) => {
        if (index === 0) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });
    
    // S'assurer qu'un seul bouton est actif au démarrage
    tabButtons.forEach((btn, index) => {
        if (index === 0) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Ajouter des événements de clic aux boutons d'onglets
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Retirer la classe active de tous les boutons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // Ajouter la classe active au bouton cliqué
            button.classList.add('active');
            
            // Récupérer l'onglet à afficher
            const tab = button.getAttribute('data-tab');
            
            // Masquer tous les onglets
            galleryTabs.forEach(tab => tab.classList.remove('active'));
            
            // Afficher l'onglet correspondant
            document.getElementById(`${tab}-gallery`).classList.add('active');
        });
    });
    
    // Initialiser les images de la galerie pour qu'elles ouvrent le modal
    const galleryImages = document.querySelectorAll('.gallery-item img');
    galleryImages.forEach(img => {
        img.addEventListener('click', function() {
            const modal = document.querySelector('.modal');
            const modalContent = document.querySelector('.modal-content');
            
            if (modal && modalContent) {
                modalContent.src = this.src;
                modal.classList.add('active');
            }
        });
    });
    
    // Initialiser les sliders après avoir configuré les onglets
    initGallerySliders();
}

/**
 * Initialise les sliders de galerie d'images
 * Crée les boutons de navigation et les indicateurs pour chaque galerie
 */
function initGallerySliders() {
    console.log('Initialisation des sliders de galerie');
    
    const galleryTabs = document.querySelectorAll('.gallery-tab');
    
    galleryTabs.forEach(tab => {
        const galleryItems = tab.querySelectorAll('.gallery-item');
        if (galleryItems.length === 0) return;
        
        // Définir la première image comme active
        let currentIndex = 0;
        galleryItems[0].classList.add('active');
        
        // Créer les boutons de navigation
        const prevBtn = document.createElement('button');
        prevBtn.className = 'gallery-nav prev-btn';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        
        const nextBtn = document.createElement('button');
        nextBtn.className = 'gallery-nav next-btn';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        
        // Créer les indicateurs
        const indicators = document.createElement('div');
        indicators.className = 'slider-indicators';
        
        for (let i = 0; i < galleryItems.length; i++) {
            const indicator = document.createElement('span');
            indicator.className = i === 0 ? 'indicator active' : 'indicator';
            indicator.dataset.index = i;
            indicators.appendChild(indicator);
            
            // Événement pour cliquer sur un indicateur
            indicator.addEventListener('click', () => {
                showSlide(i);
            });
        }
        
        // Ajouter les éléments au DOM
        tab.appendChild(prevBtn);
        tab.appendChild(nextBtn);
        tab.appendChild(indicators);
        
        // Fonction pour afficher une diapositive spécifique
        function showSlide(index) {
            if (index < 0) index = galleryItems.length - 1;
            if (index >= galleryItems.length) index = 0;
            
            // Cacher toutes les images et indicateurs actifs
            galleryItems.forEach(item => item.classList.remove('active'));
            indicators.querySelectorAll('.indicator').forEach(ind => ind.classList.remove('active'));
            
            // Afficher l'image active et l'indicateur actif
            galleryItems[index].classList.add('active');
            indicators.querySelectorAll('.indicator')[index].classList.add('active');
            
            currentIndex = index;
        }
        
        // Événements pour les boutons de navigation
        prevBtn.addEventListener('click', () => {
            showSlide(currentIndex - 1);
        });
        
        nextBtn.addEventListener('click', () => {
            showSlide(currentIndex + 1);
        });
        
        // Navigation avec les flèches du clavier
        document.addEventListener('keydown', (e) => {
            if (tab.classList.contains('active')) {
                if (e.key === 'ArrowLeft') {
                    showSlide(currentIndex - 1);
                } else if (e.key === 'ArrowRight') {
                    showSlide(currentIndex + 1);
                }
            }
        });
    });
}

/******************************************************************************
 * SECTION 4: INITIALISATION AU CHARGEMENT DE LA PAGE
 ******************************************************************************/

/**
 * Fonction principale exécutée au chargement du DOM
 * Initialise tous les composants de la page
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - script principal exécuté');
    
    try {
        // Initialisation des fonctions de base
        initBookingButtons();
        initFloatingPriceBanner();
        initMap();
        
        // Initialisation des contenus et des langues
        updateContentByLanguage('fr'); // Langue par défaut
        initLanguageSwitcher();
        initCalendar();
        
        // Initialisation des composants visuels
        createImageModal();
        initGalleryTabs(); // Cette fonction appelle également initGallerySliders()
        
        // Initialisation des animations
        animateSections();
        
        // Vérifier le hash pour le défilement vers la carte
        checkHashForMap();
        
    } catch (error) {
        console.error('Erreur lors de l\'initialisation:', error);
    }
});

/**
 * Vérifier le hash au chargement complet de la page
 * Assure que la page défile correctement vers la carte si #map est dans l'URL
 */
window.addEventListener('load', checkHashForMap);