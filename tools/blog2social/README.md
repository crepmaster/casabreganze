# Blog2Social publisher (`publish.py`)

Utilitaire CLI robuste pour publier des **vidéos** (TikTok, etc.) via l'API Blog2Social,
issu d'un test réel de bout en bout. Aucun secret en dur — tout par variables d'environnement.

## Variables d'environnement
| Variable | Rôle |
|---|---|
| `B2S_SERVICE_TOKEN` | **Requis.** Identifie l'application (plan Business). |
| `B2S_ACCESS_TOKEN` | **Requis pour publier.** Le token de session **durable** qui porte tes réseaux connectés. ⚠️ Un token re-minté à neuf via `/user/auth` = utilisateur **vide** (0 réseau). Stocke et réutilise toujours le même. |

## Commandes
```bash
# Lister les comptes connectés (et voir instant_sharing)
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py accounts

# Générer un lien d'autorisation pour connecter un réseau (ex. TikTok = 36)
B2S_SERVICE_TOKEN=… python3 publish.py connect --network-id 36

# Publier une vidéo
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py post \
    --account 2179158 --video https://exemple.com/clip.mp4 --caption "Texte #hashtags"
```
Codes de sortie de `post` : `0` publié · `1` erreur · `2` TikTok a refusé · `3` timeout (encore en traitement).

## Ce que le script encapsule (découvertes validées)
- **Auth** : `/user/auth` mint un access_token (longue durée) ; la connexion réseau est liée à CE token.
- **Création** : `client_user_network_id` doit être **dans chaque `b2s_posts`** (sinon HTTP 500).
- **Vidéo** : si `video_upload_type == 1` (cas TikTok) → **upload physique en chunks** requis (l'URL seule
  ne suffit pas). Chunks divisibles par 8 ; toutes égales sauf la dernière.
- **Anti-bot** : l'hôte d'upload exige un **User-Agent navigateur** ; sinon page de challenge HTML → on
  ré-essaie chaque chunk jusqu'à réponse JSON.

## Limite connue — TikTok
La **publication directe** TikTok n'est pas garantie : si la connexion a `instant_sharing: 0`, TikTok
refuse le publish direct (`/video/check` → `state:1`). Mode réaliste pour TikTok : **brouillon +
validation manuelle**, ou compte Business + scope `video.publish` + app approuvée. (LinkedIn/FB/Pinterest
restent en auto ; Reddit en manuel.)
