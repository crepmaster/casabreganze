# EasyRest.eu — Plan de refonte SEO & technique

> Document de cadrage. Statut : **proposition à valider**. Aucune ligne de code applicative n'est écrite tant que ce plan n'est pas approuvé.
> Branche de travail : `claude/jolly-rubin-5bmrgh`.

---

## 0. Décisions actées

| Sujet | Décision |
|---|---|
| Plateforme | **On reste sur WordPress** et on consolide l'existant (le SEO cassé vient de l'implémentation, pas de WP). |
| Modèle de réservation | **Modèle 2 — « direct d'abord + boutons OTA »** : on abandonne MotoPress/WP Hotel Booking. Pas de moteur de réservation lourd ni de paiement instantané. Bouton « Réserver en direct (-X %) » (demande via formulaire/WhatsApp → confirmation manuelle → **Stripe Payment Link**), + boutons Airbnb/Booking en secours. |
| Disponibilités | **Une seule source de vérité** : le flux **iCal agrégé fourni par l'agence** (Airbnb + Booking + autres canaux), importé en **lecture seule**. URL configurable, branchée quand l'agence la transmet. Pas de synchro bidirectionnelle. |
| Prix Booking + réduction | On garde `easyrest-core` pour afficher « -X % vs Booking » (le direct étant autorisé). On **sécurise juridiquement** (pré-cache + disclaimer, ou tarif de référence manuel) et on supprime les *bridges* MotoPress/WPHB. |
| Articles auto Milan | On garde `easyrest-content-engine`, on le **branche sur Polylang** et on corrige le SEO multilingue. |
| Multilingue | **Polylang** + vraies URLs `/fr/ /en/ /it/ /es/` + `hreflang`. On supprime l'i18n JavaScript. |

---

## 1. Diagnostic synthétique (état actuel)

### Ce qui fonctionne
- Thème `easyrest-child` (sur Astra) cohérent : pages vitrine, galerie, formulaire de réservation, fast-checkout.
- `easyrest-core` : scraping Booking.com robuste (microservice Node/Puppeteer + 2 fallbacks), cache, rate-limiting, calcul de la réduction.
- `easyrest-content-engine` : pipeline LLM complet (file d'attente, cron 5 min, scoring qualité 6 critères, adaptateur SEO RankMath/Yoast/AIOSEO/SEOPress, maillage interne, images Pexels, distribution réseaux). **C'est la brique la plus aboutie.**

### Problèmes par ordre d'impact SEO
1. 🔴 **Multilingue en JavaScript côté client** (`language-switcher.js`, attributs `data-fr/en/it/es`). Une seule URL, pas de `hreflang`, HTML servi en FR. → **Google n'indexe que le FR.**
2. 🔴 **Articles multilingues non reliés** : 4 articles régénérés/semaine, slugs identiques, sans `hreflang`. → autorité diluée, duplicate content.
3. 🔴 **Aucun JSON-LD** : ni `Apartment`/`LodgingBusiness`, ni `Offer`, ni `AggregateRating`, ni `Article`/`BreadcrumbList`. → pas de rich snippets.
4. 🟠 `layout.css` jamais chargé proprement ; images Pexels sans `alt` ; valeurs en dur (WhatsApp FR, e-mail, géo) ; meta-descriptions sans contrôle de longueur ; jQuery chargé partout ; polices Google sans cache-busting.
5. 🟠 **Risque juridique** du scraping Booking (CGU). Or le prix direct est *ton* tarif : Booking ne sert que de comparaison.
6. 🟠 **Complexité parasite** : les bridges MPHB/WPHB de `easyrest-core` n'existent que pour forcer le prix dans un moteur tiers. Supprimés avec le plugin de réservation maison.

---

## 2. Architecture cible

```
WordPress (back-office + rendu)
├── Thème easyrest-child (vitrine + widget réservation + checkout natif)
├── Polylang                  → URLs /fr /en /it /es + hreflang + sitemap multilingue
├── RankMath (ou Yoast)       → meta, sitemap, OG ; complété par notre JSON-LD
├── easyrest-core             → service de prix (Booking + réduction) — bridges MPHB/WPHB SUPPRIMÉS
├── easyrest-booking [NOUVEAU]→ LÉGER : calendrier (iCal agence en lecture seule), demande de résa
│                               directe, Stripe Payment Link, boutons OTA, taxe séjour, e-mails
└── easyrest-content-engine   → articles auto, branché Polylang, SEO durci
```

Principe : **chaque brique a une seule responsabilité**, plus aucune surcharge d'un moteur tiers.

---

## 3. Nouveau plugin `easyrest-booking` (version LÉGÈRE — modèle 2)

Objectif : **pas un moteur de réservation**, mais un module sobre « direct d'abord » : afficher les disponibilités (depuis l'iCal de l'agence), capter une **demande de réservation directe**, encaisser via **Stripe Payment Link** après confirmation manuelle, et proposer les **boutons OTA** en secours. Il remplace MotoPress et supprime les *bridges* de `easyrest-core`.

**Ce qu'on NE construit PAS** (volontairement) : pas de paiement instantané intégré, pas de verrou de disponibilité en temps réel, pas de synchro iCal bidirectionnelle. L'agence reste maître des canaux ; on la consomme en lecture seule.

### 3.1 Modèle de données (2 tables custom légères)

`wp_easyrest_booking_requests` (les demandes de résa directe = des leads qualifiés)
| champ | type | note |
|---|---|---|
| id, reference | PK, varchar | référence lisible (ex. `ER-2026-0042`) |
| status | enum | `new` / `quoted` / `confirmed` / `declined` / `cancelled` |
| check_in, check_out | date | dates demandées |
| adults, children | int | |
| guest_name/email/phone | varchar | |
| lang | varchar(2) | langue (e-mails) |
| estimated_total, currency | decimal | devis indicatif (prix direct -X %) |
| city_tax_amount | decimal | taxe de séjour Milan (indicatif) |
| stripe_payment_link | varchar | lien de paiement envoyé après confirmation |
| payment_status | enum | `unpaid` / `deposit_paid` / `paid` |
| created_at, updated_at | datetime | |

`wp_easyrest_blocked_dates` (disponibilités importées — **lecture seule**)
| champ | note |
|---|---|
| id, start, end | intervalle bloqué |
| source | `ical_agency` (principal) / `manual` |
| uid | UID iCal (dédup/suppression sur annulation) |

> **Disponibilité = affichage** : une nuit est « prise » si un `blocked_dates` la chevauche. Sert à griser le calendrier et à empêcher une demande sur des dates déjà bloquées. Pas de réservation atomique : la confirmation finale reste **manuelle**, contre le calendrier de l'agence.

### 3.2 Import iCal (lecture seule, source = agence)
- **URL iCal configurable** dans les réglages (fournie par l'agence plus tard ; agrège déjà Airbnb + Booking + autres).
- Cron (15–30 min) lit le flux → upsert `blocked_dates` par `uid` ; supprime le blocage quand un `uid` disparaît (annulation).
- Parsing : parser RFC 5545 minimal (`VEVENT` : `DTSTART/DTEND/UID/STATUS`) ou `sabre/vobject` vendoré. Gestion `DATE` vs `DATE-TIME`/fuseaux.
- **Pas d'export iCal** : on ne réinjecte rien dans les canaux (c'est le rôle de l'agence).

### 3.3 Parcours « réserver en direct »
1. Le visiteur saisit dates + voyageurs → calendrier grise les dates bloquées.
2. Devis indicatif affiché : **prix direct (-X % vs Booking)** via `easyrest-core` + taxe de séjour.
3. Bouton **« Réserver en direct »** → formulaire (coordonnées) → crée un `booking_request` (`status=new`) + notifie le propriétaire (e-mail/WhatsApp).
4. Le propriétaire **confirme manuellement** (dates libres côté agence), génère un **Stripe Payment Link** (acompte ou total) et l'envoie au client.
5. Paiement réglé → `status=confirmed`. (Optionnel : webhook Stripe pour passer `payment_status` automatiquement.)

### 3.4 Boutons OTA (secours)
- Sous le bloc « direct », boutons **Airbnb / Booking** avec lien profond (dates pré-remplies si possible).
- Pour le visiteur qui préfère la confiance/les avis des plateformes — on ne perd pas la résa.

### 3.5 Tarification & taxe
- `easyrest-core` fournit le prix de référence Booking − réduction (ou tarif manuel).
- **Taxe de séjour Milan** : ~€4–5 / pers / nuit, plafond 14 nuits, mineurs exonérés. Affichée à titre indicatif (paramétrable).

### 3.6 Paiement (Stripe Payment Links — ZÉRO intégration lourde)
- **Stripe Payment Link** créé manuellement (ou via API si on veut l'automatiser plus tard). Pas de Payment Intents custom, pas de checkout maison.
- Webhook optionnel pour marquer `paid`. Remboursement/annulation gérés côté dashboard Stripe.

### 3.7 E-mails & admin
- E-mails multilingues : accusé de demande au client + notification au propriétaire.
- Admin : calendrier (dispos agence), liste des demandes, réglages (URL iCal, % réduction, taxe, n° WhatsApp, liens OTA).

### 3.8 Front
- Le widget existant (dates + voyageurs → prix) reçoit le **bloc demande directe + boutons OTA**.
- **Supprime** `checkout-prefill.js` et les hacks de cookies MotoPress du fast-checkout.

### 3.9 REST API
`/availability` (calendrier) · `/quote` (devis indicatif) · `/booking-request` (création) · (option) `/stripe-webhook`.

### 3.10 Ce que ça supprime dans `easyrest-core`
- `integrations/class-mphb-pricing-bridge.php` et `class-wphb-pricing-bridge.php` → **supprimés** (≈700 lignes des plus fragiles). Le service de prix est consommé directement par `easyrest-booking`.

---

## 4. Refonte SEO multilingue (Polylang)
- Installer **Polylang**, supprimer `language-switcher.js` et les attributs `data-{lang}`.
- Vraies URLs `/fr/ /en/ /it/ /es/`, `hreflang` + `x-default` automatiques, sitemap multilingue (RankMath + Polylang).
- Brancher `easyrest-content-engine` : `pll_set_post_language()` + `pll_save_post_translations()` pour **relier les 4 versions** d'un article (le content-engine sait déjà détecter Polylang).
- Maillage interne **intra-langue uniquement** (aujourd'hui un article FR peut pointer vers un EN).

---

## 5. Données structurées (JSON-LD)
- Page appartement : `Apartment` / `LodgingBusiness` (adresse, géo, équipements, `priceRange`, `AggregateRating`) + `FAQPage`.
- `Offer` avec le prix direct dynamique.
- Guides : `Article` + `BreadcrumbList` (l'`Article` est déjà partiellement posé via RankMath).
- Validation via Rich Results Test avant déploiement.

---

## 6. Technique & performance
- Corriger l'enqueue de `layout.css` (orphelin).
- **Alt text auto** sur images Pexels (généré par le LLM dans le pipeline).
- Canonical par langue ; OG `locale` + `locale:alternate`.
- Core Web Vitals : auto-héberger les polices (cache-busting), différer/retirer jQuery quand inutile, object-cache (Redis), critical CSS, vérifier les `width/height` images (anti-CLS).
- Externaliser les valeurs en dur (WhatsApp, e-mail, géo) en réglages.

---

## 7. Content-engine — durcissement SEO
- Validation longueur meta-description (120–160), densité du mot-clé focus (2–3 %).
- Rate-limiting des appels LLM (anti-429).
- Clusters thématiques : page pilier « Que faire à Milan » + guides hebdo pointant vers elle.
- **Option qualité** : migrer la génération d'OpenAI `gpt-4o-mini` vers **Claude** (Haiku 4.5 pour le coût, Opus 4.8 pour la qualité long-form/multilingue). À chiffrer selon volume.

---

## 8. Prix Booking — sécurisation
- Pré-cache nocturne des prix (zéro attente utilisateur, moins de risque de blocage WAF).
- Disclaimer « Prix Booking actualisé il y a X min » (transparence/trust + signal de fraîcheur).
- **Décision à prendre** : continuer le scraping (avec disclaimer) **ou** saisir un tarif de référence Booking manuel (juridiquement bien plus sûr).

---

## 9. Roadmap & estimations

Deux chantiers **parallélisables** : le SEO multilingue ne dépend pas de la réservation.

| Lot | Contenu | Effort | Dépend de |
|---|---|---|---|
| **A. SEO multilingue** | Polylang, URLs, hreflang, branchement content-engine, sitemap | ~1 sem. | — |
| **B. JSON-LD** | Schemas appart/offer/article/breadcrumb | ~3–4 j | A (URLs) |
| **C. Technique/CWV** | layout.css, alt, polices, cache, valeurs en dur | ~1 sem. | — |
| **D. Plugin `easyrest-booking` (léger)** | 2 tables, import iCal lecture seule, calendrier, demande directe, Stripe Payment Link, boutons OTA, taxe séjour, e-mails, admin | **~1–1,5 sem.** | iCal agence (URL fournie plus tard) |
| **E. Nettoyage core** | suppression bridges MPHB/WPHB, branchement prix natif | ~2–3 j | D |
| **F. Content-engine SEO** | meta/densité, rate-limit, clusters, (option Claude) | ~1 sem. | A |

**Séquencement conseillé** : A → B (gain SEO rapide, indépendant) en parallèle de D (désormais bien plus court). Puis E, C, F.

**Ordre de grandeur total** : ~4–5 semaines de dev focalisé. Le passage au **modèle 2** allège fortement le lot D (plus de moteur d'instant-booking ni de synchro bidirectionnelle).

> Note : le lot D peut démarrer **sans** l'URL iCal de l'agence (l'URL est un simple réglage). On code tout, on branche le flux quand l'agence le transmet.

---

## 10. Risques & décisions ouvertes
1. **iCal agence** ✅ *résolu* : l'agence fournira le lien de synchro (flux agrégé). Import en lecture seule, URL configurable. Le lot D démarre sans attendre.
2. **Contrat agence** : confirmer que la **vente en direct (-X %) est autorisée** par le contrat de gestion (pas de clause d'exclusivité de distribution). Condition du modèle 2.
3. **Stripe** : compte UE prêt pour générer des **Payment Links** ? Politique d'annulation/remboursement à définir.
4. **Migration** : des réservations MotoPress en production à reprendre, ou refonte avant mise en ligne ? (impacte le lot E).
5. **Scraping Booking** : on tranche scraping (+ disclaimer) vs **tarif de référence manuel** pour afficher le « -X % » (§8).
6. **Migration LLM** : on reste OpenAI ou on bascule Claude (§7) ? Décision coût/qualité.
7. **Polylang** : version gratuite suffit pour 4 langues ; WPML seulement si besoin de traduction de chaînes avancée.

---

## 11. Prochaine étape
À ta validation de ce plan (ou de ses ajustements), je démarre par le **Lot A (SEO multilingue)** — plus gros levier, indépendant — et je cadre en parallèle le **Lot D (`easyrest-booking`)** avec un schéma de données et une liste de tickets détaillée.
