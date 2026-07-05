# Déploiement — easyrest.eu

Architecture :

```
Cloudflare Pages  ──►  SITE statique (easyrest-site)  — build auto à chaque push, CDN, HTTPS
        │
        └── /quote ──►  VPS IONOS/Plesk  ──►  microservice PRIX (Node + Puppeteer)
                                              https://prix.easyrest.eu/quote
```

- Domaine : **easyrest.eu** (déjà dans `easyrest-site/astro.config.mjs → site`).
- Le site est **statique** : un article ajouté = un commit → Pages rebuild et publie tout seul.

---

## 1. Mettre easyrest.eu sur Cloudflare

1. Cloudflare → **Add a site** → `easyrest.eu` → suivre l'import DNS.
2. Chez le **registrar** (IONOS) : remplacer les **nameservers** par ceux fournis par Cloudflare.
3. Attendre la propagation (statut « Active » dans Cloudflare).

## 2. Héberger le site sur Cloudflare Pages

1. Cloudflare → **Workers & Pages → Create → Pages → Connect to Git** → repo `casabreganze`.
2. Réglages de build (monorepo — l'app est dans un sous-dossier) :
   | Champ | Valeur |
   |---|---|
   | Production branch | `main` (ou la branche déployée) |
   | Root directory | `easyrest-site` |
   | Framework preset | Astro |
   | Build command | `npm run build` |
   | Build output directory | `dist` |
   | Variable d'env | `NODE_VERSION = 22` (ou via le `.nvmrc` présent) |
3. **Save and Deploy** → une URL `…​.pages.dev` s'ouvre (aperçu immédiat).
4. **Custom domains** → ajouter `easyrest.eu` (et `www.easyrest.eu`). Cloudflare crée les
   enregistrements DNS et le certificat automatiquement. Rediriger `www` → apex si voulu
   (Rules → Redirect Rules).

> Le build Pages a internet → le fetch iCal Guesty au build fonctionne.
> `ANTHROPIC_API_KEY` n'est **pas** nécessaire sur Pages (il sert au moteur dans la GitHub Action).

## 3. Microservice prix sur le VPS (Plesk)

But : exposer `https://prix.easyrest.eu/quote`.

### a. Sous-domaine + reverse proxy (Plesk)
1. Plesk → **Websites & Domains → Add Subdomain** → `prix.easyrest.eu`.
2. Sur ce sous-domaine → **Apache & nginx Settings → Additional nginx directives** :
   ```nginx
   location / {
       proxy_pass http://127.0.0.1:3456;
       proxy_set_header Host $host;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_read_timeout 60s;
   }
   ```
3. Activer le **certificat SSL** (Let's Encrypt) sur `prix.easyrest.eu`.
   > DNS : dans Cloudflare, créer un `A prix → <IP du VPS>` (proxy Cloudflare **désactivé**,
   > nuage gris, pour laisser Plesk gérer le TLS et éviter les timeouts sur le scrape long).

### b. Service Node (SSH)
```bash
# Sur le VPS, en SSH :
cd /var/www/vhosts/easyrest.eu/.../services/price-scraper   # ou un git clone du repo
npm ci

# Dépendances système de Chromium (Debian/Ubuntu) :
sudo apt-get update && sudo apt-get install -y \
  libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 \
  libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2

# Config
cp .env.example .env
#  → EASYREST_TOKEN=<génère: node -e "console.log(require('crypto').randomBytes(32).toString('hex'))">
#  → DEFAULT_HOTEL_URL=https://www.booking.com/hotel/it/easy-rest-affitti-brevi-italia.fr.html
#  → DISCOUNT_PERCENT=15
#  → ALLOWED_ORIGINS=https://easyrest.eu,https://www.easyrest.eu

# Lancement + démarrage auto (PM2)
npm i -g pm2
pm2 start ecosystem.config.js
pm2 save && pm2 startup     # relance au reboot
```
Vérifier : `curl https://prix.easyrest.eu/health` → `{"status":"ok",...}`.

## 4. Relier le site au service

Dans `easyrest-site/src/config.ts` :
```ts
quoteEndpoint: 'https://prix.easyrest.eu/quote',
```
Commit → Pages redéploie → le simulateur affiche le **vrai prix Booking −15 %**
(et retombe sur l'estimation si le service est indisponible).

## 5. Publication des articles (rappel)

- GitHub Action hebdo (`.github/workflows/weekly-content.yml`) : génère → build → commit → push.
- Le push déclenche le **rebuild Cloudflare Pages** → l'article est en ligne. Hands-off.
- Agenda insolite ponctuel : `cd engine && ANTHROPIC_API_KEY=… npm run events`, puis commit/push.

## Points d'attention

- **Puppeteer + Cloudflare** : garder `prix.easyrest.eu` en **DNS-only** (nuage gris). Le scrape
  peut durer ~40 s ; le proxy Cloudflare couperait avant.
- **CGU Booking** : le scraping reste en zone grise et peut casser ; le repli « prix estimé »
  (tarif de référence dans `config.ts`) protège l'UX.
- **Secrets** : `ANTHROPIC_API_KEY` → secret GitHub Actions ; `EASYREST_TOKEN` → `.env` du VPS
  (jamais commité). `.env` est ignoré par git.
