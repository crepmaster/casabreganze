# EasyRest Booking.com Scraper Microservice

Microservice Node.js pour récupérer les prix depuis Booking.com via Puppeteer.

## Installation

### 1. Installer les dépendances

```bash
cd scraper-node
npm install
```

### 2. Configurer le fichier .env

```bash
cp .env.example .env
nano .env
```

**IMPORTANT:** Générer un token sécurisé :
```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

Copier le token dans `.env` (EASYREST_TOKEN) et dans WordPress (EasyRest > Settings > Microservice Token).

### 3. Lancer le serveur

**Mode développement :**
```bash
npm start
```

**Mode production (PM2) :**
```bash
npm run pm2:start
```

## Endpoints

### GET /health
Vérification de l'état du service.

```bash
curl http://localhost:3456/health
```

### POST /booking/price
Récupérer le prix depuis Booking.com.

```bash
curl -X POST http://localhost:3456/booking/price \
  -H "Content-Type: application/json" \
  -H "X-EasyRest-Token: YOUR_TOKEN" \
  -d '{
    "checkin": "2026-02-10",
    "checkout": "2026-02-15",
    "adults": 2,
    "children": 0
  }'
```

**Réponse succès :**
```json
{
  "success": true,
  "price": 598,
  "currency": "EUR",
  "nights": 5,
  "source": "booking",
  "timestamp": "2026-01-16T17:00:00Z"
}
```

## Configuration WordPress

Dans **EasyRest > Settings > Advanced** :

1. **Microservice URL:** `http://localhost:3456` (ou URL de production)
2. **Microservice Token:** Le même token que dans `.env`

## Déploiement o2switch

1. Upload du dossier `scraper-node` via FTP/SSH
2. Se connecter en SSH
3. Installer les dépendances : `npm install`
4. Configurer `.env`
5. Lancer avec PM2 : `pm2 start ecosystem.config.js`
6. Sauvegarder la config PM2 : `pm2 save`
7. Configurer le démarrage auto : `pm2 startup`

## Logs

```bash
# Voir les logs en temps réel
pm2 logs easyrest-scraper

# Voir les fichiers de logs
cat logs/output.log
cat logs/error.log
```

## Commandes PM2

```bash
pm2 start ecosystem.config.js  # Démarrer
pm2 stop easyrest-scraper      # Arrêter
pm2 restart easyrest-scraper   # Redémarrer
pm2 delete easyrest-scraper    # Supprimer
pm2 status                     # État
pm2 monit                      # Monitoring
```
