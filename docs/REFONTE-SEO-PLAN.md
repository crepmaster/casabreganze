# EasyRest.eu — Plan de refonte SEO & technique

> Document de cadrage. Statut : **proposition à valider**. Aucune ligne de code applicative n'est écrite tant que ce plan n'est pas approuvé.
> Branche de travail : `claude/jolly-rubin-5bmrgh`.

---

## 0. Décisions actées

| Sujet | Décision |
|---|---|
| Plateforme | **On reste sur WordPress** et on consolide l'existant (le SEO cassé vient de l'implémentation, pas de WP). |
| Moteur de réservation | **On abandonne MotoPress/WP Hotel Booking** et on développe un **plugin maison léger `easyrest-booking`** dédié à un appartement unique. |
| Prix Booking + réduction | On garde `easyrest-core`, mais on **sécurise juridiquement** (pré-cache + disclaimer, ou tarif de référence manuel) et on supprime les *bridges* MotoPress/WPHB. |
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
├── easyrest-booking [NOUVEAU]→ réservation 1 appart : dispo, iCal, Stripe, taxe séjour, e-mails, admin
└── easyrest-content-engine   → articles auto, branché Polylang, SEO durci
```

Principe : **chaque brique a une seule responsabilité**, plus aucune surcharge d'un moteur tiers.

---

## 3. Nouveau plugin `easyrest-booking` (cœur du chantier)

Objectif : moteur de réservation **mono-unité**, sobre, qui remplace MotoPress et supprime les bridges de `easyrest-core`.

### 3.1 Modèle de données (tables custom, pas de CPT pour les réservations)

`wp_easyrest_bookings`
| champ | type | note |
|---|---|---|
| id, reference | PK, varchar | référence lisible (ex. `ER-2026-0042`) |
| status | enum | `pending` / `confirmed` / `cancelled` / `expired` |
| check_in, check_out | date | intervalle semi-ouvert `[in, out)` |
| adults, children | int | |
| guest_name/email/phone | varchar | |
| lang | varchar(2) | langue de la réservation (e-mails) |
| total_amount, currency | decimal | |
| city_tax_amount | decimal | taxe de séjour Milan |
| deposit_amount | decimal | acompte si activé |
| payment_status | enum | `unpaid` / `deposit_paid` / `paid` / `refunded` |
| stripe_payment_intent | varchar | idempotence paiement |
| source | enum | `direct` / `booking` / `airbnb` / `manual` |
| hold_expires_at | datetime | TTL d'un `pending` (≈15 min) |
| created_at, updated_at | datetime | |

`wp_easyrest_blocked_dates` (dispos externes + blocages manuels)
| champ | note |
|---|---|
| id, start, end | intervalle bloqué |
| source | `ical_booking` / `ical_airbnb` / `manual` |
| uid | UID iCal (dédup/suppression sur annulation) |

> **Disponibilité = calcul** : une nuit est libre si aucune réservation `confirmed`/`pending non expiré` ni `blocked_dates` ne la chevauche. Règle de chevauchement : `check_in < autre.check_out AND check_out > autre.check_in` (le jour de départ reste réservable pour le suivant).

### 3.2 Disponibilité & verrouillage (anti-double-réservation)
- Création d'un `pending` dans une **transaction SQL** : `SELECT ... FOR UPDATE` sur la plage, vérif chevauchement, insert `pending` avec `hold_expires_at`.
- Confirmation à la réception du paiement (webhook Stripe).
- Cron de nettoyage : libère les `pending` expirés.

### 3.3 Synchro canaux iCal (partie critique)
- **Export** : flux iCal secret `/easyrest-ical/{token}.ics` listant les réservations `confirmed` → importé dans Booking.com et Airbnb (ils bloquent ces dates).
- **Import** : cron (15–30 min) lit les URLs iCal de Booking.com + Airbnb → upsert `blocked_dates` par `uid` ; suppression du blocage quand un `uid` disparaît (annulation).
- Parsing : `sabre/vobject` vendoré, **ou** parser RFC 5545 minimal (uniquement `VEVENT` : `DTSTART/DTEND/UID/STATUS`). Gestion fuseaux/`DATE` vs `DATE-TIME`.

### 3.4 Tarification
- Réutilise `easyrest-core` (prix Booking − réduction, ou tarif manuel) pour le devis.
- **Taxe de séjour Milan (imposta di soggiorno)** : ~€4–5 / personne / nuit, **plafonnée à 14 nuits**, **exonération des mineurs**. Paramétrable (montant, plafond, âge d'exonération).
- Acompte configurable (% ou montant fixe) ou paiement total.

### 3.5 Paiement (Stripe)
- Stripe **Payment Intents / Checkout**. Acompte ou total.
- Webhook `/wp-json/easyrest/v1/stripe-webhook` : `payment_intent.succeeded` → confirme la réservation (idempotent via `event.id`). Remboursement à l'annulation.

### 3.6 E-mails & admin
- E-mails multilingues : confirmation client + notification propriétaire (`wp_mail`, option SMTP transactionnel).
- Admin : vue calendrier, liste des réservations, blocage/réservation manuels, réglages (taxe, acompte, URLs iCal, clés Stripe).

### 3.7 Front
- Le widget existant (dates + voyageurs → prix) gagne une **étape checkout native** (coordonnées + Stripe).
- **Supprime** `checkout-prefill.js` et les hacks de cookies MotoPress du fast-checkout.

### 3.8 REST API
`/availability` · `/quote` · `/booking` (création) · `/stripe-webhook` · `/ical/{token}.ics`.

### 3.9 Ce que ça supprime dans `easyrest-core`
- `integrations/class-mphb-pricing-bridge.php` et `class-wphb-pricing-bridge.php` → **supprimés** (≈700 lignes des plus fragiles). Le service de prix devient consommé directement par `easyrest-booking`.

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
| **D. Plugin `easyrest-booking`** | data model, dispo+lock, **iCal**, **Stripe**, taxe séjour, e-mails, admin, checkout | **~2–3 sem.** | — |
| **E. Nettoyage core** | suppression bridges MPHB/WPHB, branchement prix natif | ~2–3 j | D |
| **F. Content-engine SEO** | meta/densité, rate-limit, clusters, (option Claude) | ~1 sem. | A |

**Séquencement conseillé** : A → B (gain SEO rapide, indépendant) en parallèle de D (le plus long). Puis E, C, F.

**Ordre de grandeur total** : ~5–7 semaines de dev focalisé, le plugin de réservation étant le poste le plus lourd et le plus risqué.

---

## 10. Risques & décisions ouvertes
1. **iCal** : fiabilité de la synchro Booking/Airbnb = condition sine qua non (un double-booking = sinistre). Tests réels requis avec les deux plateformes.
2. **Migration** : y a-t-il des réservations MotoPress en production à migrer, ou la refonte précède-t-elle la mise en ligne ? (impacte le lot E).
3. **Stripe** : compte Stripe Milan/UE prêt ? gestion remboursements/annulations à cadrer (politique d'annulation).
4. **Scraping Booking** : on tranche scraping vs tarif manuel (§8).
5. **Migration LLM** : on reste OpenAI ou on bascule Claude (§7) ? Décision coût/qualité.
6. **Polylang** : version gratuite suffit pour 4 langues ; WPML seulement si besoin de traduction de chaînes avancée.

---

## 11. Prochaine étape
À ta validation de ce plan (ou de ses ajustements), je démarre par le **Lot A (SEO multilingue)** — plus gros levier, indépendant — et je cadre en parallèle le **Lot D (`easyrest-booking`)** avec un schéma de données et une liste de tickets détaillée.
