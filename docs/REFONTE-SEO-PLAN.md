# EasyRest.eu — Plan de refonte (v2 — sans WordPress / Astro + Jamstack)

> Statut : **proposition à valider**. Remplace la v1 (consolidation WordPress).
> Branche de travail : `claude/jolly-rubin-5bmrgh`.
>
> **Pourquoi cette v2 ?** La réservation s'est réduite à du **lead-gen** (WhatsApp + PayPal de l'agence), donc l'argument n°1 qui retenait sur WordPress (ne pas reconstruire un moteur de réservation) a disparu. Le contenu est **auto-généré** (pas besoin d'admin WYSIWYG), et le multilingue + la performance — les deux points les plus cassés — sont mieux servis par un site statique. On abandonne WordPress.

---

## 0. Décisions actées

| Sujet | Décision |
|---|---|
| Plateforme | **Sans WordPress.** Site **Astro** statique (Jamstack) + quelques fonctions serverless / endpoints n8n pour le dynamique. |
| Hébergement | Site statique sur **Cloudflare Pages / Netlify (quasi gratuit)** ou sur le **Hetzner** existant. Le microservice de prix et n8n restent sur Hetzner. |
| Réservation | **Lead-gen pur.** Bouton « Réserver en direct (-X %) » → **WhatsApp** (deep-link pré-rempli) → confirmation agence (**SLA < 15 min**) → encaissement **sur le PayPal de l'agence**. Boutons Airbnb/Booking en secours. |
| Host-of-record / conformité | **L'agence** (elle encaisse via son PayPal) → c'est elle qui porte le **CIN**, l'**Alloggiati Web**, la **taxe de séjour** et la fiscalité. Le site EasyRest = apporteur de clients, **zéro obligation légale de location**. |
| Disponibilités | **iCal Guesty** (fourni par l'agence, en attente) importé en **lecture seule** → calendrier consultatif. En intérim : **pas de calendrier**, mode « demande ». |
| Contenu | Pipeline de génération **réimplémenté** (cœur du content-engine porté en Node/TS) → Markdown/MDX + frontmatter → git → build Astro. |
| Génération LLM | Migration vers **Claude** recommandée (Haiku 4.5 pour le coût, Opus 4.8 pour la qualité long-form/multilingue) — meilleure que `gpt-4o-mini` actuel. |
| Multilingue | i18n **natif Astro** : URLs `/fr/ /en/ /it/ /es/` + `hreflang` + `x-default`. |
| Diffusion | **n8n** (sur Hetzner) lit le `/feed.xml` du site et fait le fan-out vers les réseaux. Reddit traité **manuellement** (anti-autopromo strict). |

---

## 1. Architecture cible

```
Dépôt git (contenu Markdown + code Astro)
        │  (commit déclenche le build)
        ▼
Astro build ──► Site statique (Cloudflare Pages / Netlify / Hetzner)
   ├── Pages vitrine appart (galerie, équipements, avis) — i18n fr/en/it/es + hreflang + JSON-LD
   ├── Guides Milan (articles hebdo) — clusters SEO + Article/Breadcrumb schema
   ├── /feed.xml (RSS natif @astrojs/rss)
   └── Bloc réservation : bouton WhatsApp + boutons OTA (+ calendrier iCal plus tard)

Services dynamiques (séparés, légers)
   ├── Microservice PRIX (réutilisé tel quel) : Node/Puppeteer sur Hetzner → "-X% vs Booking"
   ├── Capture de lead : Cloudflare Worker / fonction Netlify / webhook n8n → stockage (DB/Sheet/n8n)
   ├── Génération de contenu : script Node/TS (Claude) planifié (n8n cron ou GitHub Action) → écrit le Markdown, commit
   ├── Import iCal Guesty (à venir) : n8n/fonction → availability JSON → calendrier lecture seule
   └── Syndication : n8n lit /feed.xml → LinkedIn/FB/Pinterest (Reddit = manuel)
```

Principe : **statique par défaut, dynamique seulement là où c'est nécessaire**, chaque service isolé.

---

## 2. Front Astro

- **i18n natif** (`astro:i18n`) : routes `/fr/ /en/ /it/ /es/`, génération des `hreflang` + `x-default`, sélecteur de langue = vrais liens (plus de JS qui réécrit le DOM).
- **Données structurées (JSON-LD)** : `Apartment`/`LodgingBusiness` (adresse, géo, équipements, `priceRange`, `AggregateRating`) + `FAQPage` sur la page appart ; `Article` + `BreadcrumbList` sur les guides ; `Offer` avec le prix direct.
- **Performance / Core Web Vitals** : HTML pré-rendu, images optimisées (déjà en WebP), polices auto-hébergées, JS minimal (îlots Astro), zéro jQuery. Cible : 90+ Lighthouse.
- **Sitemap** multilingue (`@astrojs/sitemap`) + `robots.txt`.
- Réutilise le **design et les contenus** des templates actuels (`page-easyrest-*.php`) convertis en composants Astro, et la **galerie photo** existante.

---

## 3. Pipeline de contenu (remplace le content-engine)

Objectif : conserver la **valeur** du content-engine (génération cadrée, scoring qualité, SEO on-page, maillage, multilingue) en la portant hors de WordPress.

- **Génération** : script Node/TS appelant **Claude**. Réutilise les **prompts JSON** existants et la **logique du quality scorer** (portée en TS).
- **Sortie** : fichiers **Markdown/MDX** avec frontmatter (`title`, `description`, `lang`, `slug`, `translations`, `focus_keyword`, `image`, `alt`) commités dans le dépôt.
- **Multilingue SEO** : les 4 versions d'un article partagent une clé de traduction dans le frontmatter → Astro génère les `hreflang` qui les relient (corrige le problème actuel des 4 articles non reliés).
- **Images** : récupération (Pexels ou tes propres photos) dans les assets du dépôt, **avec alt text généré** (corrige l'absence d'alt actuelle).
- **Planification** : **1 article/semaine** déclenché par **n8n (cron)** ou une **GitHub Action** → génère → commit → build → déploie.
- **Relecture optionnelle** : si tu veux valider avant publication, l'article est commité sur une branche / en `draft: true` dans le frontmatter ; sinon publication directe.
- **Maillage interne** intra-langue + clusters : page pilier « Que faire à Milan » + guides hebdo pointant vers elle.

---

## 4. Réservation (lead-gen)

- **Bouton « Réserver en direct (-X %) »** → ouvre **WhatsApp** (`wa.me/...?text=...`) pré-rempli (dates, voyageurs, prix estimé).
- **Capture du lead** avant l'ouverture WhatsApp (fonction serverless / webhook n8n) : dates, voyageurs, source, horodatage, statut → pilotage + analytics + **instrumentation du SLA** (relance si non confirmé sous X min).
- **Devis indicatif** : prix direct (-X % vs Booking) via le **microservice de prix réutilisé** + taxe de séjour indicative.
- **Boutons Airbnb / Booking** en secours (lien profond, dates pré-remplies si possible).
- **Encaissement** : **PayPal de l'agence** (envoyé dans la conversation). Le site n'encaisse rien → pas de Stripe, pas de conformité à porter.
- **Calendrier** : **désactivé en intérim** (mode « demande »). Activé en lecture seule quand l'iCal Guesty arrive ; **confirmation humaine maintenue avant encaissement** (lag iCal).

---

## 5. Syndication (diffusion sortante)

- Le site **expose** `/feed.xml` (RSS natif).
- **n8n** (Hetzner) lit ce feed → fan-out **LinkedIn / Facebook / Pinterest / X**.
- **Reddit = manuel**, au cas par cas (subreddits pertinents, vraie participation). L'auto-post de liens commerciaux = bannissement quasi assuré → **exclu du fan-out automatique**.

---

## 6. Réutilisé vs abandonné

**Réutilisé (peu/pas de réécriture)**
- `scraper-node/` : microservice de prix Node/Puppeteer **tel quel**.
- Prompts JSON, logique de quality scoring (portée en TS).
- Photos/galerie, contenus et design des pages, copies multilingues existantes.

**Abandonné**
- Thème WordPress + Astra, plugins WP (`easyrest-content-engine` côté WP, bridges MPHB/WPHB de `easyrest-core`), i18n JavaScript (`language-switcher.js`), hacks de checkout MotoPress, MotoPress lui-même.

---

## 7. Hébergement & déploiement

- **Site statique** : Cloudflare Pages ou Netlify (build sur push git, CDN mondial, HTTPS, **gratuit** à ce volume) — ou sur Hetzner si tu préfères tout centraliser.
- **Microservice prix + n8n** : restent sur **Hetzner** (déjà en place).
- **CI/CD** : push sur la branche → build → déploiement automatique. RSS + sitemap régénérés à chaque build.

---

## 8. Roadmap & estimations

| Lot | Contenu | Effort |
|---|---|---|
| **A. Socle Astro** | Setup, i18n/hreflang, pages vitrine (depuis les templates actuels), galerie, JSON-LD, sitemap, RSS | ~1–1,5 sem. |
| **B. Pipeline contenu** | Port du cœur de génération (Claude), prompts, quality scorer, sortie Markdown+frontmatter, planif n8n/Action, alt images | ~1–1,5 sem. |
| **C. Réservation lead-gen** | Bouton WhatsApp, capture de lead (fonction), devis via microservice prix, boutons OTA | ~3–4 j |
| **D. Syndication** | `/feed.xml` + workflow n8n de fan-out (hors Reddit) | ~2–3 j |
| **E. Calendrier iCal** | Import Guesty (quand URL dispo) → availability JSON → calendrier lecture seule | ~2–3 j (différé) |

**Séquencement** : A → B en priorité (le SEO et le contenu sont le moteur de trafic), C en parallèle, D ensuite, E quand l'iCal Guesty arrive.

**Ordre de grandeur total** : ~3–4 semaines (hors E différé). Plus léger que la v1 grâce au lead-gen et au statique.

---

## 9. Mode intérim (avant l'iCal Guesty, ~10 jours)

- **Pas de calendrier.** Le site ne promet aucune dispo.
- Bouton WhatsApp → demande → **l'agence confirme sous < 15 min** → lien PayPal agence.
- Honnête, déployable immédiatement, **zéro risque de double-booking côté vitrine**.
- À verrouiller avec l'agence : le **SLA** (acté < 15 min) et la **mécanique PayPal** (acté : compte de l'agence).

---

## 10. Décisions ouvertes / pré-requis
1. **Contenu** ✅ : on réécrit le cœur de génération hors WP (décision « sans WordPress »).
2. **Édition manuelle** : toi seul (technique) touches au contenu, ou un non-dev doit pouvoir relire/corriger ? → si non-dev requis, prévoir un CMS git-based léger (Decap/TinaCMS) sur Astro.
3. **Hébergement front** : Cloudflare Pages/Netlify (gratuit) ou Hetzner ?
4. **iCal Guesty** : URL à obtenir de l'agence (ou export direct Booking/Airbnb si les comptes sont à ton nom).
5. **Scraping Booking** : on garde le microservice (+ disclaimer) ou tarif de référence manuel pour le « -X % » ?
6. **Stockage des leads** : DB, Google Sheet, Airtable, ou directement dans n8n ?
7. **Contrat agence** : confirmer que la vente en direct (apport de client à tarif négocié, encaissement agence) est OK contractuellement.

---

## 11. Prochaine étape
À ta validation : démarrage du **Lot A (socle Astro)** + **Lot B (pipeline contenu)** en priorité — c'est le moteur de trafic SEO — avec le mode intérim de réservation (§9) déployable tout de suite.
