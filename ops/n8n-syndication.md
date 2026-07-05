# Syndication réseaux sociaux via n8n

Objectif : chaque nouvel article publié sur le site est **automatiquement** poussé
vers LinkedIn / Facebook / Pinterest, **sans intervention** — hors la connexion
initiale des comptes et la re-autorisation périodique des jetons OAuth (expiration
~60–90 j côté plateformes, incompressible).

Le site n'exécute pas la syndication : il **expose des flux RSS**, et n8n (déjà en
place sur le Hetzner de l'agence) les lit et fait le fan-out.

## Flux RSS exposés par le site

Un flux par langue (générés nativement par `@astrojs/rss`) :

- `https://easyrest.eu/fr/rss.xml`
- `https://easyrest.eu/en/rss.xml`
- `https://easyrest.eu/it/rss.xml`
- `https://easyrest.eu/es/rss.xml`

Pour la diffusion sociale, on part en général du **flux FR** (langue canonique) ;
ajouter les autres langues = dupliquer la branche avec une autre URL.

## Architecture du workflow n8n

```
RSS Feed Trigger (poll /fr/rss.xml, ex. toutes les 30 min)
        │  (n8n déduplique nativement : ne renvoie que les items nouveaux)
        ▼
Set (compose le texte du post : titre + lien + 1 phrase)
        ├──► LinkedIn  (node LinkedIn, « Create a post »)
        ├──► Facebook  (node Facebook Graph API, « Create post » sur la Page)
        └──► Pinterest (node HTTP Request → API Pinterest, ou node dédié)
```

Reddit reste **manuel** (l'auto-post de liens commerciaux = bannissement quasi
assuré) — exclu du fan-out.

## Mise en place (une seule fois)

1. **Importer** le squelette ci-dessous dans n8n (Workflows → Import from File/JSON).
2. **Connecter les credentials** (OAuth) : LinkedIn, Facebook (Page), Pinterest.
   C'est la seule étape « manuelle » ; ensuite tout est automatique.
3. **Activer** le workflow. Le RSS Trigger déclenche à chaque nouvel article.
4. **Re-auth** : quand un token expire, n8n signale une erreur d'exécution —
   reconnecter le credential concerné (quelques clics). C'est le seul entretien.

## Squelette n8n à importer

> ⚠️ Les noms/versions de nodes peuvent varier selon ta version de n8n ; ce
> squelette est un point de départ à ajuster à l'import. Les credentials sont
> volontairement laissés vides — à renseigner dans l'UI.

```json
{
  "name": "EasyRest — Syndication RSS → réseaux",
  "nodes": [
    {
      "parameters": {
        "pollTimes": { "item": [{ "mode": "everyX", "value": 30, "unit": "minutes" }] },
        "feedUrl": "https://easyrest.eu/fr/rss.xml"
      },
      "id": "rss-trigger",
      "name": "Nouvel article (RSS FR)",
      "type": "n8n-nodes-base.rssFeedReadTrigger",
      "typeVersion": 1,
      "position": [260, 300]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "post-text",
              "name": "postText",
              "type": "string",
              "value": "={{ $json.title }}\n\n{{ $json.contentSnippet || '' }}\n\n{{ $json.link }}"
            }
          ]
        }
      },
      "id": "compose",
      "name": "Composer le post",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [520, 300]
    },
    {
      "parameters": { "text": "={{ $json.postText }}", "additionalFields": {} },
      "id": "linkedin",
      "name": "LinkedIn",
      "type": "n8n-nodes-base.linkedIn",
      "typeVersion": 1,
      "position": [800, 180],
      "credentials": { "linkedInOAuth2Api": { "id": "", "name": "LinkedIn EasyRest" } }
    },
    {
      "parameters": {
        "graphApiVersion": "v19.0",
        "node": "page",
        "edge": "feed",
        "options": {},
        "queryParameters": {
          "parameter": [{ "name": "message", "value": "={{ $json.postText }}" }]
        }
      },
      "id": "facebook",
      "name": "Facebook Page",
      "type": "n8n-nodes-base.facebookGraphApi",
      "typeVersion": 1,
      "position": [800, 300],
      "credentials": { "facebookGraphApi": { "id": "", "name": "Facebook Page EasyRest" } }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://api.pinterest.com/v5/pins",
        "authentication": "genericCredentialType",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"board_id\": \"<TON_BOARD_ID>\",\n  \"title\": {{ JSON.stringify($json.title) }},\n  \"link\": {{ JSON.stringify($json.link) }}\n}"
      },
      "id": "pinterest",
      "name": "Pinterest",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [800, 420],
      "credentials": { "oAuth2Api": { "id": "", "name": "Pinterest EasyRest" } }
    }
  ],
  "connections": {
    "Nouvel article (RSS FR)": { "main": [[{ "node": "Composer le post", "type": "main", "index": 0 }]] },
    "Composer le post": {
      "main": [[
        { "node": "LinkedIn", "type": "main", "index": 0 },
        { "node": "Facebook Page", "type": "main", "index": 0 },
        { "node": "Pinterest", "type": "main", "index": 0 }
      ]]
    }
  },
  "settings": {},
  "active": false
}
```

## Pourquoi ce n'est pas dans le dépôt / la GitHub Action

Poster sur les réseaux exige des jetons OAuth vivants et une re-auth périodique :
l'exécuter depuis un cron GitHub imposerait de stocker et rafraîchir ces jetons en
secret CI, plus fragile qu'un n8n déjà fait pour ça. n8n gère nativement la
déduplication RSS, la reconnexion OAuth et le monitoring d'exécution. La frontière
est donc : **le dépôt publie le site (100 % auto) ; n8n diffuse (auto après
connexion initiale)**.
