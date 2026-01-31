<?php
/**
 * Template Name: EasyRest Milan Classic
 *
 * Landing page fidèle au layout.html original.
 * Compatible Astra - utilise get_header() et get_footer().
 * Galerie avec système d'onglets, carte Google Maps lazy-loaded.
 *
 * @package EasyRest_Child
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get theme assets URL
$assets_url = get_stylesheet_directory_uri() . '/assets';
$img_url = $assets_url . '/images/gallery';

get_header();

// Logo URL - using existing logo file
$logo_url = $assets_url . '/images/gallery/logo esayrest.png';
?>

<div id="primary" class="content-area easyrest-milan-classic">
    <main id="main" class="site-main">

        <!-- Custom Header with Logo and Language Selector -->
        <header class="custom-header">
            <div class="container">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="EasyRest" onerror="this.style.display='none'">
                    <span class="site-logo-text">EasyRest</span>
                </a>

                <nav class="header-nav">
                    <div class="language-selector">
                        <button data-lang="fr" class="active">FR</button>
                        <button data-lang="en">EN</button>
                        <button data-lang="it">IT</button>
                        <button data-lang="es">ES</button>
                        <button data-lang="pt">PT</button>
                        <button data-lang="zh">中文</button>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <header class="classic-header">
            <div class="container">
                <h1 class="main-title"
                    data-fr="Doux foyer à Milan"
                    data-en="Sweet Home in Milan"
                    data-it="Dolce casa a Milano"
                    data-es="Dulce hogar en Milán"
                    data-pt="Doce lar em Milão"
                    data-zh="米兰优雅公寓">Doux foyer à Milan</h1>

                <p class="subtitle"
                   data-fr="Via Giovanni di Breganze 1, 20152 Milan"
                   data-en="Via Giovanni di Breganze 1, 20152 Milan"
                   data-it="Via Giovanni di Breganze 1, 20152 Milano"
                   data-es="Via Giovanni di Breganze 1, 20152 Milán"
                   data-pt="Via Giovanni di Breganze 1, 20152 Milão"
                   data-zh="米兰温馨之家">Via Giovanni di Breganze 1, 20152 Milan</p>

                <div class="hero-cta">
                    <a href="#easyrest-booking" class="btn btn-primary btn-hero"
                       data-fr="Vérifier la disponibilité"
                       data-en="Check Availability"
                       data-it="Verifica disponibilità"
                       data-es="Verificar disponibilidad"
                       data-pt="Verificar disponibilidade"
                       data-zh="查看空房">Vérifier la disponibilité</a>
                </div>
            </div>
        </header>

        <!-- Description de l'appartement -->
        <section class="apartment-overview">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Présentation de l'appartement"
                    data-en="Apartment Overview"
                    data-it="Panoramica dell'appartamento"
                    data-es="Descripción del Apartamento"
                    data-pt="Visão Geral do Apartamento"
                    data-zh="公寓概览">Présentation de l'appartement</h2>
                <div class="overview-content">
                    <div class="overview-text">
                        <p data-fr="Bienvenue dans notre charmant appartement situé au cœur de Milan. Idéalement placé à proximité des transports en commun et des principales attractions touristiques, ce logement vous offre tout le confort nécessaire pour un séjour agréable."
                           data-en="Welcome to our charming apartment located in the heart of Milan. Ideally situated near public transport and main tourist attractions, this accommodation offers all the comfort you need for a pleasant stay."
                           data-it="Benvenuti nel nostro affascinante appartamento situato nel cuore di Milano. Idealmente situato vicino ai trasporti pubblici e alle principali attrazioni turistiche, questo alloggio offre tutto il comfort necessario per un soggiorno piacevole."
                           data-es="Bienvenido a nuestro encantador apartamento situado en el corazón de Milán. Idealmente ubicado cerca del transporte público y las principales atracciones turísticas, este alojamiento ofrece toda la comodidad necesaria para una estancia agradable."
                           data-pt="Bem-vindo ao nosso charmoso apartamento localizado no coração de Milão. Idealmente situado perto de transportes públicos e das principais atrações turísticas, este alojamento oferece todo o conforto necessário para uma estadia agradável."
                           data-zh="欢迎来到我们位于米兰市中心的迷人公寓。地理位置优越，靠近公共交通和主要旅游景点，为您提供舒适愉快的住宿体验。">Bienvenue dans notre charmant appartement situé au cœur de Milan. Idéalement placé à proximité des transports en commun et des principales attractions touristiques, ce logement vous offre tout le confort nécessaire pour un séjour agréable.</p>

                        <p data-fr="L'appartement dispose d'une chambre avec lit double, d'un salon lumineux avec coin cuisine équipé, et d'une salle de bain moderne. Vous profiterez également du Wi-Fi gratuit, de la climatisation et de tous les équipements essentiels."
                           data-en="The apartment features a bedroom with a double bed, a bright living room with an equipped kitchenette, and a modern bathroom. You will also enjoy free Wi-Fi, air conditioning, and all essential amenities."
                           data-it="L'appartamento dispone di una camera con letto matrimoniale, un luminoso soggiorno con angolo cottura attrezzato e un bagno moderno. Potrete inoltre usufruire del Wi-Fi gratuito, dell'aria condizionata e di tutti i comfort essenziali."
                           data-es="El apartamento cuenta con un dormitorio con cama doble, una luminosa sala de estar con cocina equipada y un baño moderno. También disfrutará de Wi-Fi gratuito, aire acondicionado y todas las comodidades esenciales."
                           data-pt="O apartamento dispõe de um quarto com cama de casal, uma luminosa sala de estar com kitchenette equipada e uma casa de banho moderna. Também desfrutará de Wi-Fi gratuito, ar condicionado e todas as comodidades essenciais."
                           data-zh="公寓设有一间配备双人床的卧室、一间明亮的客厅（配有设备齐全的厨房角）和一间现代化的浴室。您还可以享受免费WiFi、空调和所有基本设施。">L'appartement dispose d'une chambre avec lit double, d'un salon lumineux avec coin cuisine équipé, et d'une salle de bain moderne. Vous profiterez également du Wi-Fi gratuit, de la climatisation et de tous les équipements essentiels.</p>

                        <p data-fr="Parfait pour les couples ou les voyageurs solo, cet appartement est votre point de départ idéal pour explorer Milan et ses environs, notamment lors des Jeux Olympiques d'hiver Milan-Cortina 2026."
                           data-en="Perfect for couples or solo travelers, this apartment is your ideal starting point for exploring Milan and its surroundings, especially during the Milan-Cortina 2026 Winter Olympics."
                           data-it="Perfetto per coppie o viaggiatori singoli, questo appartamento è il punto di partenza ideale per esplorare Milano e i suoi dintorni, soprattutto durante le Olimpiadi Invernali Milano-Cortina 2026."
                           data-es="Perfecto para parejas o viajeros solos, este apartamento es su punto de partida ideal para explorar Milán y sus alrededores, especialmente durante los Juegos Olímpicos de Invierno Milán-Cortina 2026."
                           data-pt="Perfeito para casais ou viajantes a solo, este apartamento é o seu ponto de partida ideal para explorar Milão e arredores, especialmente durante os Jogos Olímpicos de Inverno Milão-Cortina 2026."
                           data-zh="非常适合情侣或独自旅行者，这间公寓是您探索米兰及其周边地区的理想起点，特别是在2026年米兰-科尔蒂纳冬季奥运会期间。">Parfait pour les couples ou les voyageurs solo, cet appartement est votre point de départ idéal pour explorer Milan et ses environs, notamment lors des Jeux Olympiques d'hiver Milan-Cortina 2026.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Galeries de photos -->
        <section class="gallery-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Galeries Photos"
                    data-en="Photo Galleries"
                    data-it="Gallerie Fotografiche"
                    data-es="Galerías de Fotos"
                    data-pt="Galerias de Fotos"
                    data-zh="图片库">Galeries Photos</h2>

                <div class="gallery-tabs">
                    <button class="tab-btn active" data-tab="building">
                        <i class="fas fa-building"></i>
                        <span data-fr="Immeuble" data-en="Building" data-it="Edificio" data-es="Edificio" data-pt="Edifício" data-zh="建筑">Immeuble</span>
                    </button>
                    <button class="tab-btn" data-tab="living">
                        <i class="fas fa-couch"></i>
                        <span data-fr="Living" data-en="Living Room" data-it="Soggiorno" data-es="Sala de estar" data-pt="Sala de estar" data-zh="客厅">Living</span>
                    </button>
                    <button class="tab-btn" data-tab="kitchen">
                        <i class="fas fa-utensils"></i>
                        <span data-fr="Coin cuisine" data-en="Kitchen Area" data-it="Angolo cottura" data-es="Área de cocina" data-pt="Área da cozinha" data-zh="厨房区域">Coin cuisine</span>
                    </button>
                    <button class="tab-btn" data-tab="bedroom">
                        <i class="fas fa-bed"></i>
                        <span data-fr="Chambre" data-en="Bedroom" data-it="Camera da letto" data-es="Dormitorio" data-pt="Quarto" data-zh="卧室">Chambre</span>
                    </button>
                    <button class="tab-btn" data-tab="bathroom">
                        <i class="fas fa-bath"></i>
                        <span data-fr="Salle de bain" data-en="Bathroom" data-it="Bagno" data-es="Baño" data-pt="Banheiro" data-zh="浴室">Salle de bain</span>
                    </button>
                </div>

                <div class="gallery-content">
                    <!-- Galerie Immeuble -->
                    <div class="gallery-tab active" id="building-gallery">
                        <div class="gallery-slider">
                            <div class="gallery-item active">
                                <img src="<?php echo esc_url($img_url); ?>/building/entreimmeuble.webp" alt="Entrée de l'immeuble" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/building/hallimmeuble.webp" alt="Hall de l'immeuble" loading="lazy">
                            </div>
                        </div>
                        <button class="gallery-nav prev-btn"><i class="fas fa-chevron-left"></i></button>
                        <button class="gallery-nav next-btn"><i class="fas fa-chevron-right"></i></button>
                        <div class="slider-indicators"></div>
                    </div>

                    <!-- Galerie Living -->
                    <div class="gallery-tab" id="living-gallery">
                        <div class="gallery-slider">
                            <div class="gallery-item active">
                                <img src="<?php echo esc_url($img_url); ?>/living/livingvuensemble.webp" alt="Vue d'ensemble du salon" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/living/livingvuensemble_cotemur.webp" alt="Salon côté mur" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/living/livingvuensemble_cotecouloir.webp" alt="Salon côté couloir" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/living/livingvuebalcon.webp" alt="Vue du balcon" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/living/salleamangevueliving.webp" alt="Salle à manger" loading="lazy">
                            </div>
                        </div>
                        <button class="gallery-nav prev-btn"><i class="fas fa-chevron-left"></i></button>
                        <button class="gallery-nav next-btn"><i class="fas fa-chevron-right"></i></button>
                        <div class="slider-indicators"></div>
                    </div>

                    <!-- Galerie Cuisine -->
                    <div class="gallery-tab" id="kitchen-gallery">
                        <div class="gallery-slider">
                            <div class="gallery-item active">
                                <img src="<?php echo esc_url($img_url); ?>/kitchen/angle_cuisine.webp" alt="Cuisine" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/kitchen/tableamangervuecuisne.webp" alt="Vue cuisine" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/kitchen/cuisinewelcomekit.webp" alt="Kit de bienvenue" loading="lazy">
                            </div>
                        </div>
                        <button class="gallery-nav prev-btn"><i class="fas fa-chevron-left"></i></button>
                        <button class="gallery-nav next-btn"><i class="fas fa-chevron-right"></i></button>
                        <div class="slider-indicators"></div>
                    </div>

                    <!-- Galerie Chambre -->
                    <div class="gallery-tab" id="bedroom-gallery">
                        <div class="gallery-slider">
                            <div class="gallery-item active">
                                <img src="<?php echo esc_url($img_url); ?>/bedroom/chambrevuearmoire.webp" alt="Chambre vue armoire" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bedroom/chambrevuehaut.webp" alt="Chambre vue de haut" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bedroom/chambrevuemur.webp" alt="Chambre vue mur" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bedroom/chambrevufenetre.webp" alt="Chambre vue fenêtre" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bedroom/armoire.webp" alt="Armoire" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bedroom/lit.webp" alt="Lit" loading="lazy">
                            </div>
                        </div>
                        <button class="gallery-nav prev-btn"><i class="fas fa-chevron-left"></i></button>
                        <button class="gallery-nav next-btn"><i class="fas fa-chevron-right"></i></button>
                        <div class="slider-indicators"></div>
                    </div>

                    <!-- Galerie Salle de bain -->
                    <div class="gallery-tab" id="bathroom-gallery">
                        <div class="gallery-slider">
                            <div class="gallery-item active">
                                <img src="<?php echo esc_url($img_url); ?>/bathroom/salledebainvueentree.webp" alt="Salle de bain" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bathroom/bidet.webp" alt="Bidet" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bathroom/douche.webp" alt="Douche" loading="lazy">
                            </div>
                            <div class="gallery-item">
                                <img src="<?php echo esc_url($img_url); ?>/bathroom/evier.webp" alt="Évier" loading="lazy">
                            </div>
                        </div>
                        <button class="gallery-nav prev-btn"><i class="fas fa-chevron-left"></i></button>
                        <button class="gallery-nav next-btn"><i class="fas fa-chevron-right"></i></button>
                        <div class="slider-indicators"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Pourquoi réserver ici -->
        <section class="why-book-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Pourquoi réserver chez nous"
                    data-en="Why Book With Us"
                    data-it="Perché prenotare con noi"
                    data-es="Por qué reservar con nosotros"
                    data-pt="Por que reservar conosco"
                    data-zh="为什么选择我们">Pourquoi réserver chez nous</h2>

                <div class="reasons-grid">
                    <div class="reason-card">
                        <div class="reason-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <h3 class="reason-title" data-fr="Emplacement idéal" data-en="Ideal Location" data-it="Posizione ideale" data-es="Ubicación ideal" data-pt="Localização ideal" data-zh="理想位置">Emplacement idéal</h3>
                        <p class="reason-description" data-fr="À seulement 800m du métro et proche de toutes les attractions principales de Milan." data-en="Just 800m from the metro and close to all main attractions in Milan." data-it="A soli 800m dalla metropolitana e vicino a tutte le principali attrazioni di Milano." data-es="A solo 800m del metro y cerca de todas las principales atracciones de Milán." data-pt="A apenas 800m do metrô e perto de todas as principais atrações de Milão." data-zh="距离地铁站仅800米，靠近米兰所有主要景点。">À seulement 800m du métro et proche de toutes les attractions principales de Milan.</p>
                    </div>

                    <div class="reason-card">
                        <div class="reason-icon"><i class="fas fa-home"></i></div>
                        <h3 class="reason-title" data-fr="Confort et élégance" data-en="Comfort and Elegance" data-it="Comfort ed eleganza" data-es="Comodidad y elegancia" data-pt="Conforto e elegância" data-zh="舒适与优雅">Confort et élégance</h3>
                        <p class="reason-description" data-fr="Appartement entièrement équipé avec des finitions modernes et tout le confort nécessaire." data-en="Fully equipped apartment with modern finishes and all necessary comforts." data-it="Appartamento completamente attrezzato con finiture moderne e tutti i comfort necessari." data-es="Apartamento totalmente equipado con acabados modernos y todas las comodidades necesarias." data-pt="Apartamento totalmente equipado com acabamentos modernos e todo o conforto necessário." data-zh="全套配备的公寓，现代装修，提供所有必要的舒适设施。">Appartement entièrement équipé avec des finitions modernes et tout le confort nécessaire.</p>
                    </div>

                    <div class="reason-card">
                        <div class="reason-icon"><i class="fas fa-star"></i></div>
                        <h3 class="reason-title" data-fr="Excellentes critiques" data-en="Excellent Reviews" data-it="Recensioni eccellenti" data-es="Excelentes reseñas" data-pt="Excelentes avaliações" data-zh="极佳评价">Excellentes critiques</h3>
                        <p class="reason-description" data-fr="Note moyenne de 4.8/5 basée sur les avis de nos précédents voyageurs satisfaits." data-en="Average rating of 4.8/5 based on reviews from our previous satisfied travelers." data-it="Valutazione media di 4.8/5 basata sulle recensioni dei nostri precedenti viaggiatori soddisfatti." data-es="Calificación promedio de 4.8/5 basada en reseñas de nuestros viajeros previos satisfechos." data-pt="Classificação média de 4.8/5 baseada em avaliações dos nossos viajantes anteriores satisfeitos." data-zh="基于我们之前满意旅客的评价，平均评分为4.8/5。">Note moyenne de 4.8/5 basée sur les avis de nos précédents voyageurs satisfaits.</p>
                    </div>

                    <div class="reason-card">
                        <div class="reason-icon"><i class="fas fa-wifi"></i></div>
                        <h3 class="reason-title" data-fr="Tout équipé" data-en="Fully Equipped" data-it="Completamente attrezzato" data-es="Totalmente equipado" data-pt="Totalmente equipado" data-zh="全套设备">Tout équipé</h3>
                        <p class="reason-description" data-fr="WiFi haut débit, cuisine complète, climatisation et tous les équipements pour un séjour parfait." data-en="High-speed WiFi, full kitchen, air conditioning and all amenities for a perfect stay." data-it="WiFi ad alta velocità, cucina completa, aria condizionata e tutti i comfort per un soggiorno perfetto." data-es="WiFi de alta velocidad, cocina completa, aire acondicionado y todas las comodidades para una estancia perfecta." data-pt="WiFi de alta velocidade, cozinha completa, ar condicionado e todas as comodidades para uma estadia perfeita." data-zh="高速WiFi、完整厨房、空调以及所有让您完美入住的设施。">WiFi haut débit, cuisine complète, climatisation et tous les équipements pour un séjour parfait.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Équipements et Services -->
        <section class="amenities-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Équipements et Services"
                    data-en="Amenities and Services"
                    data-it="Servizi e Comfort"
                    data-es="Comodidades y Servicios"
                    data-pt="Comodidades e Serviços"
                    data-zh="设施与服务">Équipements et Services</h2>

                <div class="amenities-grid">
                    <!-- Général -->
                    <div class="amenity-category">
                        <h3><i class="fas fa-home"></i><span data-fr="Général" data-en="General" data-it="Generale" data-es="General" data-pt="Geral" data-zh="基本设施">Général</span></h3>
                        <ul class="amenities-list">
                            <li><i class="fas fa-wifi"></i><span data-fr="Wi-Fi gratuit" data-en="Free Wi-Fi" data-it="Wi-Fi gratuito" data-es="Wi-Fi gratis" data-pt="Wi-Fi gratuito" data-zh="免费无线网络">Wi-Fi gratuit</span></li>
                            <li><i class="fas fa-snowflake"></i><span data-fr="Climatisation" data-en="Air conditioning" data-it="Aria condizionata" data-es="Aire acondicionado" data-pt="Ar condicionado" data-zh="空调">Climatisation</span></li>
                            <li><i class="fas fa-temperature-high"></i><span data-fr="Chauffage" data-en="Heating" data-it="Riscaldamento" data-es="Calefacción" data-pt="Aquecimento" data-zh="暖气">Chauffage</span></li>
                            <li><i class="fas fa-leaf"></i><span data-fr="Petit balcon" data-en="Small balcony" data-it="Piccolo balcone" data-es="Pequeño balcón" data-pt="Pequena varanda" data-zh="小阳台">Petit balcon</span></li>
                            <li><i class="fas fa-key"></i><span data-fr="Clés privées" data-en="Private keys" data-it="Chiavi private" data-es="Llaves privadas" data-pt="Chaves privadas" data-zh="私人钥匙">Clés privées</span></li>
                        </ul>
                    </div>

                    <!-- Chambre -->
                    <div class="amenity-category">
                        <h3><i class="fas fa-bed"></i><span data-fr="Chambre" data-en="Bedroom" data-it="Camera da letto" data-es="Dormitorio" data-pt="Quarto" data-zh="卧室">Chambre</span></h3>
                        <ul class="amenities-list">
                            <li><i class="fas fa-bed"></i><span data-fr="Lit double (160×200 cm)" data-en="Double bed (160×200 cm)" data-it="Letto matrimoniale (160×200 cm)" data-es="Cama doble (160×200 cm)" data-pt="Cama de casal (160×200 cm)" data-zh="双人床 (160×200 厘米)">Lit double (160×200 cm)</span></li>
                            <li><i class="fas fa-door-closed"></i><span data-fr="Dressing" data-en="Wardrobe" data-it="Armadio" data-es="Armario" data-pt="Guarda-roupa" data-zh="衣柜">Dressing</span></li>
                            <li><i class="fas fa-fan"></i><span data-fr="Ventilateur" data-en="Fan" data-it="Ventilatore" data-es="Ventilador" data-pt="Ventilador" data-zh="风扇">Ventilateur</span></li>
                            <li><i class="fas fa-shield-alt"></i><span data-fr="Détecteur de fumée" data-en="Smoke detector" data-it="Rilevatore di fumo" data-es="Detector de humo" data-pt="Detector de fumaça" data-zh="烟雾探测器">Détecteur de fumée</span></li>
                        </ul>
                    </div>

                    <!-- Salle de bain -->
                    <div class="amenity-category">
                        <h3><i class="fas fa-bath"></i><span data-fr="Salle de bain" data-en="Bathroom" data-it="Bagno" data-es="Baño" data-pt="Banheiro" data-zh="浴室">Salle de bain</span></h3>
                        <ul class="amenities-list">
                            <li><i class="fas fa-shower"></i><span data-fr="Douche" data-en="Shower" data-it="Doccia" data-es="Ducha" data-pt="Chuveiro" data-zh="淋浴">Douche</span></li>
                            <li><i class="fas fa-toilet"></i><span data-fr="Toilettes" data-en="Toilet" data-it="WC" data-es="Inodoro" data-pt="Sanita" data-zh="马桶">Toilettes</span></li>
                            <li><i class="fas fa-tint"></i><span data-fr="Eau chaude" data-en="Hot water" data-it="Acqua calda" data-es="Agua caliente" data-pt="Água quente" data-zh="热水">Eau chaude</span></li>
                            <li><i class="fas fa-wind"></i><span data-fr="Sèche-cheveux" data-en="Hair dryer" data-it="Asciugacapelli" data-es="Secador de pelo" data-pt="Secador de cabelo" data-zh="吹风机">Sèche-cheveux</span></li>
                            <li><i class="fas fa-soap"></i><span data-fr="Produits de toilette" data-en="Toiletries" data-it="Prodotti da bagno" data-es="Artículos de tocador" data-pt="Produtos de higiene" data-zh="洗漱用品">Produits de toilette</span></li>
                        </ul>
                    </div>

                    <!-- Cuisine -->
                    <div class="amenity-category">
                        <h3><i class="fas fa-utensils"></i><span data-fr="Cuisine" data-en="Kitchen" data-it="Cucina" data-es="Cocina" data-pt="Cozinha" data-zh="厨房">Cuisine</span></h3>
                        <ul class="amenities-list">
                            <li><i class="fas fa-blender"></i><span data-fr="Cuisine équipée" data-en="Fully equipped kitchen" data-it="Cucina attrezzata" data-es="Cocina equipada" data-pt="Cozinha equipada" data-zh="设备齐全的厨房">Cuisine équipée</span></li>
                            <li><i class="fas fa-mug-hot"></i><span data-fr="Machine à café" data-en="Coffee machine" data-it="Macchina da caffè" data-es="Cafetera" data-pt="Máquina de café" data-zh="咖啡机">Machine à café</span></li>
                            <li><i class="fas fa-cube"></i><span data-fr="Réfrigérateur" data-en="Refrigerator" data-it="Frigorifero" data-es="Refrigerador" data-pt="Frigorífico" data-zh="冰箱">Réfrigérateur</span></li>
                            <li><i class="fas fa-cookie"></i><span data-fr="Four micro-ondes" data-en="Microwave" data-it="Microonde" data-es="Microondas" data-pt="Micro-ondas" data-zh="微波炉">Four micro-ondes</span></li>
                            <li><i class="fas fa-wine-glass-alt"></i><span data-fr="Vaisselle et couverts" data-en="Dishes and cutlery" data-it="Stoviglie e posate" data-es="Vajilla y cubiertos" data-pt="Louça e talheres" data-zh="餐具和刀叉">Vaisselle et couverts</span></li>
                        </ul>
                    </div>

                    <!-- Services -->
                    <div class="amenity-category">
                        <h3><i class="fas fa-concierge-bell"></i><span data-fr="Services" data-en="Services" data-it="Servizi" data-es="Servicios" data-pt="Serviços" data-zh="服务">Services</span></h3>
                        <ul class="amenities-list">
                            <li><i class="fas fa-broom"></i><span data-fr="Ménage inclus" data-en="Cleaning included" data-it="Pulizia inclusa" data-es="Limpieza incluida" data-pt="Limpeza incluída" data-zh="含清洁服务">Ménage inclus</span></li>
                            <li><i class="fas fa-tshirt"></i><span data-fr="Linge de lit" data-en="Bed linen" data-it="Biancheria da letto" data-es="Ropa de cama" data-pt="Roupa de cama" data-zh="床上用品">Linge de lit</span></li>
                            <li><i class="fas fa-bath"></i><span data-fr="Serviettes" data-en="Towels" data-it="Asciugamani" data-es="Toallas" data-pt="Toalhas" data-zh="毛巾">Serviettes</span></li>
                            <li><i class="fas fa-check-circle"></i><span data-fr="Check-in 24h/24" data-en="24-hour check-in" data-it="Check-in 24 ore su 24" data-es="Check-in 24 horas" data-pt="Check-in 24 horas" data-zh="24小时办理入住">Check-in 24h/24</span></li>
                        </ul>
                    </div>

                    <!-- Non disponible -->
                    <div class="amenity-category">
                        <h3><i class="fas fa-times-circle"></i><span data-fr="Non disponible" data-en="Not available" data-it="Non disponibile" data-es="No disponible" data-pt="Não disponível" data-zh="不可用">Non disponible</span></h3>
                        <ul class="amenities-list">
                            <li class="not-available"><i class="fas fa-smoking-ban"></i><span data-fr="Fumeurs" data-en="Smoking" data-it="Fumatori" data-es="Fumar" data-pt="Fumar" data-zh="吸烟">Fumeurs</span></li>
                            <li class="not-available"><i class="fas fa-paw"></i><span data-fr="Animaux domestiques" data-en="Pets" data-it="Animali domestici" data-es="Mascotas" data-pt="Animais de estimação" data-zh="宠物">Animaux domestiques</span></li>
                            <li class="not-available"><i class="fas fa-parking"></i><span data-fr="Parking privé" data-en="Private parking" data-it="Parcheggio privato" data-es="Estacionamiento privado" data-pt="Estacionamento privado" data-zh="私人停车场">Parking privé</span></li>
                            <li class="not-available"><i class="fas fa-swimming-pool"></i><span data-fr="Piscine" data-en="Swimming pool" data-it="Piscina" data-es="Piscina" data-pt="Piscina" data-zh="游泳池">Piscine</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Réservation - MotoPress Hotel Booking -->
        <section class="easyrest-booking-section" id="easyrest-booking">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Vérifier la disponibilité et réserver"
                    data-en="Check Availability & Book"
                    data-it="Verifica disponibilità e prenota"
                    data-es="Verificar disponibilidad y reservar"
                    data-pt="Verificar disponibilidade e reservar"
                    data-zh="查看空房并预订">Vérifier la disponibilité et réserver</h2>

                <div class="price-tag">
                    <span data-fr="À partir de 90€" data-en="From 90€" data-it="Da 90€" data-es="Desde 90€" data-pt="A partir de 90€" data-zh="起价 90€">À partir de 90€</span>
                    <span class="price-period" data-fr="/nuit" data-en="/night" data-it="/notte" data-es="/noche" data-pt="/noite" data-zh="/晚">/nuit</span>
                </div>

                <p class="price-info"
                   data-fr="Prix variable selon la saison et les disponibilités. Réservez en direct et économisez 15% par rapport aux plateformes !"
                   data-en="Price varies by season and availability. Book direct and save 15% compared to booking platforms!"
                   data-it="Il prezzo varia in base alla stagione e alla disponibilità. Prenota direttamente e risparmia il 15% rispetto alle piattaforme!"
                   data-es="El precio varía según la temporada y la disponibilidad. ¡Reserve directamente y ahorre un 15% en comparación con las plataformas!"
                   data-pt="O preço varia de acordo com a época do ano e disponibilidade. Reserve diretamente e economize 15% em relação às plataformas!"
                   data-zh="价格根据季节和可用性而变化。直接预订比平台节省15%！">Prix variable selon la saison et les disponibilités. Réservez en direct et économisez 15% par rapport aux plateformes !</p>

                <div class="motopress-booking-form-wrapper">
                    <?php
                    /**
                     * MotoPress Hotel Booking form with calendar for "EasyRest Milano – Apt 1"
                     *
                     * Using [mphb_availability] shortcode which renders:
                     * - Interactive availability calendar with clickable dates
                     * - Check-in / Check-out date pickers
                     * - Guest selection (adults/children)
                     * - Direct booking capability
                     *
                     * The 'id' parameter specifies the Accommodation Type post ID.
                     * To find your ID: WordPress Admin > Accommodations > Accommodation Types
                     * Hover over your accommodation and look at the URL - the ID is the number after "post="
                     *
                     * No WP Hotel Booking (WPHB) code is used here - MotoPress only.
                     */

                    // Get the first (and only) accommodation type ID dynamically
                    $accommodation_types = get_posts( array(
                        'post_type'      => 'mphb_room_type',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                        'fields'         => 'ids',
                    ) );

                    if ( ! empty( $accommodation_types ) ) {
                        $accommodation_id = $accommodation_types[0];
                        // Render the full booking form with interactive calendar
                        echo do_shortcode( '[mphb_availability id="' . $accommodation_id . '"]' );
                    } else {
                        // Fallback message if no accommodation found
                        echo '<p class="mphb-no-accommodation">' . esc_html__( 'Booking form temporarily unavailable. Please contact us directly.', 'flavor' ) . '</p>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <!-- Section Témoignages -->
        <section class="testimonials-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Avis de nos clients"
                    data-en="Customer Reviews"
                    data-it="Recensioni dei nostri clienti"
                    data-es="Opiniones de nuestros clientes"
                    data-pt="Opiniões dos nossos clientes"
                    data-zh="客户评价">Avis de nos clients</h2>

                <div class="testimonials-container">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <div class="testimonial-content"
                             data-fr="Appartement très bien situé, propre et confortable. Le quartier est calme et proche des transports en commun."
                             data-en="Apartment very well located, clean and comfortable. The neighborhood is quiet and close to public transportation."
                             data-it="Appartamento molto ben posizionato, pulito e confortevole. Il quartiere è tranquillo e vicino ai mezzi pubblici."
                             data-es="Apartamento muy bien ubicado, limpio y cómodo. El barrio es tranquilo y cercano al transporte público."
                             data-pt="Apartamento muito bem localizado, limpo e confortável. O bairro é tranquilo e próximo aos transportes públicos."
                             data-zh="公寓位置很好，干净舒适。周围环境安静，靠近公共交通。">Appartement très bien situé, propre et confortable. Le quartier est calme et proche des transports en commun.</div>
                        <div class="testimonial-author">
                            <div class="author-info">
                                <div class="author-name">Marie</div>
                                <div class="author-date" data-fr="Avril 2025" data-en="April 2025" data-it="Aprile 2025" data-es="Abril 2025" data-pt="Abril 2025" data-zh="2025年4月">Avril 2025</div>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <div class="testimonial-content"
                             data-fr="Séjour agréable, hôte réactif et logement conforme aux photos. Je recommande vivement."
                             data-en="Pleasant stay, responsive host and accommodation true to the photos. I highly recommend."
                             data-it="Soggiorno piacevole, host reattivo e alloggio fedele alle foto. Consiglio vivamente."
                             data-es="Estancia agradable, anfitrión atento y alojamiento fiel a las fotos. Lo recomiendo encarecidamente."
                             data-pt="Estadia agradável, anfitrião responsivo e acomodação fiel às fotos. Recomendo vivamente."
                             data-zh="愉快的住宿体验，房东反应迅速，住宿与照片相符。强烈推荐。">Séjour agréable, hôte réactif et logement conforme aux photos. Je recommande vivement.</div>
                        <div class="testimonial-author">
                            <div class="author-info">
                                <div class="author-name">Luca</div>
                                <div class="author-date" data-fr="Mars 2025" data-en="March 2025" data-it="Marzo 2025" data-es="Marzo 2025" data-pt="Março 2025" data-zh="2025年3月">Mars 2025</div>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <div class="testimonial-content"
                             data-fr="Nous avons passé un excellent week-end dans cet appartement. La cuisine est très bien équipée et le lit est confortable. L'emplacement est idéal pour explorer Milan."
                             data-en="We had a great weekend in this apartment. The kitchen is very well equipped and the bed is comfortable. The location is ideal for exploring Milan."
                             data-it="Abbiamo trascorso un ottimo fine settimana in questo appartamento. La cucina è molto ben attrezzata e il letto è comodo. La posizione è ideale per esplorare Milano."
                             data-es="Pasamos un excelente fin de semana en este apartamento. La cocina está muy bien equipada y la cama es cómoda. La ubicación es ideal para explorar Milán."
                             data-pt="Passamos um ótimo fim de semana neste apartamento. A cozinha é muito bem equipada e a cama é confortável. A localização é ideal para explorar Milão."
                             data-zh="我们在这个公寓度过了美好的周末。厨房设备齐全，床也很舒适。位置非常适合探索米兰。">Nous avons passé un excellent week-end dans cet appartement. La cuisine est très bien équipée et le lit est confortable. L'emplacement est idéal pour explorer Milan.</div>
                        <div class="testimonial-author">
                            <div class="author-info">
                                <div class="author-name">Thomas & Sophie</div>
                                <div class="author-date" data-fr="Février 2025" data-en="February 2025" data-it="Febbraio 2025" data-es="Febrero 2025" data-pt="Fevereiro 2025" data-zh="2025年2月">Février 2025</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Emplacement avec carte et points d'intérêt -->
        <section class="location-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Emplacement et Points d'intérêt"
                    data-en="Location and Points of Interest"
                    data-it="Posizione e Punti di interesse"
                    data-es="Ubicación y Puntos de interés"
                    data-pt="Localização e Pontos de Interesse"
                    data-zh="位置和周边景点">Emplacement et Points d'intérêt</h2>

                <div class="address-info">
                    <p><strong data-fr="Adresse:" data-en="Address:" data-it="Indirizzo:" data-es="Dirección:" data-pt="Endereço:" data-zh="地址:">Adresse:</strong> Via Giovanni di Breganze 1, 20152, Milan</p>
                </div>

                <div class="location-wrapper">
                    <!-- Carte Google Maps -->
                    <div class="map-container">
                        <div id="map-placeholder" style="width:100%; height:400px; background:#eee; display:flex; align-items:center; justify-content:center;">
                            <p style="color:#666;" data-fr="Chargement de la carte…" data-en="Loading map…" data-it="Caricamento mappa…" data-es="Cargando mapa…" data-pt="Carregando mapa…" data-zh="加载地图中…">Chargement de la carte…</p>
                        </div>
                    </div>

                    <!-- Points d'intérêt -->
                    <div class="poi-section">
                        <h3 data-fr="À proximité" data-en="Nearby" data-it="Nelle vicinanze" data-es="En los alrededores" data-pt="Nas proximidades" data-zh="附近设施">À proximité</h3>

                        <div class="poi-category">
                            <h4><i class="fas fa-subway"></i><span data-fr="Transports à proximité" data-en="Nearby Transport" data-it="Trasporti nelle vicinanze" data-es="Transporte cercano" data-pt="Transportes próximos" data-zh="附近交通">Transports à proximité</span></h4>
                            <ul class="poi-list">
                                <li><i class="fas fa-train"></i><span data-fr="Métro Bisceglie (Ligne M1 rouge) - 800m" data-en="Bisceglie Metro Station (Red Line M1) - 800m" data-it="Stazione Metro Bisceglie (Linea M1 rossa) - 800m" data-es="Estación de Metro Bisceglie (Línea M1 roja) - 800m" data-pt="Estação de Metrô Bisceglie (Linha M1 vermelha) - 800m" data-zh="比谢列地铁站（M1红线）- 800米">Métro Bisceglie (Ligne M1 rouge) - 800m</span></li>
                                <li><i class="fas fa-bus"></i><span data-fr="Bus 58 et 64 - arrêt Via Bisceglie/Via Nikolajevka - 400m" data-en="Bus 58 and 64 - Via Bisceglie/Via Nikolajevka stop - 400m" data-it="Autobus 58 e 64 - fermata Via Bisceglie/Via Nikolajevka - 400m" data-es="Autobús 58 y 64 - parada Via Bisceglie/Via Nikolajevka - 400m" data-pt="Ônibus 58 e 64 - parada Via Bisceglie/Via Nikolajevka - 400m" data-zh="58和64路公交车 - Via Bisceglie/Via Nikolajevka站 - 400米">Bus 58 et 64 - arrêt Via Bisceglie/Via Nikolajevka - 400m</span></li>
                                <li><i class="fas fa-car"></i><span data-fr="Périphérique ouest (Tangenziale Ovest) - 1km" data-en="Western Ring Road (Tangenziale Ovest) - 1km" data-it="Tangenziale Ovest - 1km" data-es="Circunvalación Oeste (Tangenziale Ovest) - 1km" data-pt="Anel Viário Oeste (Tangenziale Ovest) - 1km" data-zh="西环路（Tangenziale Ovest）- 1公里">Périphérique ouest (Tangenziale Ovest) - 1km</span></li>
                            </ul>
                        </div>

                        <div class="poi-category">
                            <h4><i class="fas fa-monument"></i><span data-fr="Attractions touristiques" data-en="Tourist Attractions" data-it="Attrazioni turistiche" data-es="Atracciones turísticas" data-pt="Atrações turísticas" data-zh="旅游景点">Attractions touristiques</span></h4>
                            <ul class="poi-list">
                                <li><i class="fas fa-church"></i><span data-fr="Duomo de Milan - 30 min en métro (M1 direct)" data-en="Milan Cathedral - 30 min by metro (direct M1)" data-it="Duomo di Milano - 30 min in metro (M1 diretto)" data-es="Duomo de Milán - 30 min en metro (M1 directo)" data-pt="Duomo de Milão - 30 min de metrô (M1 direto)" data-zh="米兰大教堂 - 乘地铁30分钟（M1直达）">Duomo de Milan - 30 min en métro (M1 direct)</span></li>
                                <li><i class="fas fa-futbol"></i><span data-fr="Stade San Siro - 15 min en bus (ligne 64)" data-en="San Siro Stadium - 15 min by bus (line 64)" data-it="Stadio San Siro - 15 min in autobus (linea 64)" data-es="Estadio San Siro - 15 min en autobús (línea 64)" data-pt="Estádio San Siro - 15 min de ônibus (linha 64)" data-zh="圣西罗球场 - 乘64路公交车15分钟">Stade San Siro - 15 min en bus (ligne 64)</span></li>
                                <li><i class="fas fa-shopping-bag"></i><span data-fr="Centre commercial Il Centro - 10 min en voiture" data-en="Il Centro Shopping Mall - 10 min by car" data-it="Centro Commerciale Il Centro - 10 min in auto" data-es="Centro Comercial Il Centro - 10 min en coche" data-pt="Shopping Il Centro - 10 min de carro" data-zh="Il Centro购物中心 - 开车10分钟">Centre commercial Il Centro - 10 min en voiture</span></li>
                                <li><i class="fas fa-tree"></i><span data-fr="Parc delle Cave - 1,5km à pied" data-en="Parco delle Cave - 1.5km walking distance" data-it="Parco delle Cave - 1,5km a piedi" data-es="Parque delle Cave - 1,5km a pie" data-pt="Parque delle Cave - 1,5km a pé" data-zh="Cave公园 - 步行1.5公里">Parc delle Cave - 1,5km à pied</span></li>
                            </ul>
                        </div>

                        <div class="poi-category">
                            <h4><i class="fas fa-utensils"></i><span data-fr="Services et commodités" data-en="Services and Amenities" data-it="Servizi e comodità" data-es="Servicios y comodidades" data-pt="Serviços e comodidades" data-zh="服务与设施">Services et commodités</span></h4>
                            <ul class="poi-list">
                                <li><i class="fas fa-store"></i><span data-fr="Supermarché Esselunga - 700m" data-en="Esselunga Supermarket - 700m" data-it="Supermercato Esselunga - 700m" data-es="Supermercado Esselunga - 700m" data-pt="Supermercado Esselunga - 700m" data-zh="Esselunga超市 - 700米">Supermarché Esselunga - 700m</span></li>
                                <li><i class="fas fa-pizza-slice"></i><span data-fr="Pizzeria Da Michele - 500m" data-en="Pizzeria Da Michele - 500m" data-it="Pizzeria Da Michele - 500m" data-es="Pizzería Da Michele - 500m" data-pt="Pizzaria Da Michele - 500m" data-zh="Da Michele披萨店 - 500米">Pizzeria Da Michele - 500m</span></li>
                                <li><i class="fas fa-prescription-bottle-alt"></i><span data-fr="Pharmacie San Romanello - 400m" data-en="San Romanello Pharmacy - 400m" data-it="Farmacia San Romanello - 400m" data-es="Farmacia San Romanello - 400m" data-pt="Farmácia San Romanello - 400m" data-zh="San Romanello药店 - 400米">Pharmacie San Romanello - 400m</span></li>
                                <li><i class="fas fa-hospital"></i><span data-fr="Centre médical Bisceglie - 1km" data-en="Bisceglie Medical Center - 1km" data-it="Centro Medico Bisceglie - 1km" data-es="Centro Médico Bisceglie - 1km" data-pt="Centro Médico Bisceglie - 1km" data-zh="比谢列医疗中心 - 1公里">Centre médical Bisceglie - 1km</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section finale -->
        <section class="cta-section">
            <div class="container">
                <h2 class="section-title"
                    data-fr="Réservez maintenant"
                    data-en="Book Now"
                    data-it="Prenota ora"
                    data-es="Reserve ahora"
                    data-pt="Reserve agora"
                    data-zh="立即预订">Réservez maintenant</h2>
                <p class="cta-text"
                   data-fr="Ne manquez pas cette occasion de séjourner dans cet appartement idéalement situé proche du cœur de Milan."
                   data-en="Don't miss this opportunity to stay in this ideally located apartment near the heart of Milan."
                   data-it="Non perdere questa opportunità di soggiornare in questo appartamento situato vicino al cuore di Milano."
                   data-es="No pierdas esta oportunidad de alojarte en este apartamento idealmente ubicado cerca del corazón de Milán."
                   data-pt="Não perca esta oportunidade de se hospedar neste apartamento idealmente localizado perto do coração de Milão."
                   data-zh="不要错过在米兰市中心这间位置绝佳的公寓住宿的机会。">Ne manquez pas cette occasion de séjourner dans cet appartement idéalement situé proche du cœur de Milan.</p>

                <div class="cta-buttons">
                    <a href="#easyrest-booking" class="btn btn-primary"
                       data-fr="Vérifier la disponibilité"
                       data-en="Check Availability"
                       data-it="Verifica disponibilità"
                       data-es="Verificar disponibilidad"
                       data-pt="Verificar disponibilidade"
                       data-zh="查看空房">Vérifier la disponibilité</a>
                </div>
            </div>
        </section>

        <!-- Footer simple -->
        <footer class="classic-footer">
            <div class="container">
                <p data-fr="© <?php echo date('Y'); ?> Location d'Appartement à Milan. Tous droits réservés."
                   data-en="© <?php echo date('Y'); ?> Milan Apartment Rental. All rights reserved."
                   data-it="© <?php echo date('Y'); ?> Affitto Appartamento Milano. Tutti i diritti riservati."
                   data-es="© <?php echo date('Y'); ?> Alquiler de Apartamentos en Milán. Todos los derechos reservados."
                   data-pt="© <?php echo date('Y'); ?> Aluguer de Apartamentos em Milão. Todos os direitos reservados."
                   data-zh="© <?php echo date('Y'); ?> 米兰公寓出租。保留所有权利。">© <?php echo date('Y'); ?> Location d'Appartement à Milan. Tous droits réservés.</p>
            </div>
        </footer>

    </main>
</div>

<?php
get_footer();
