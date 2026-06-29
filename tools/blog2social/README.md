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

# Diagnostiquer un refus : --debug imprime CHAQUE réponse brute (create / upload / check)
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py post --debug \
    --account 2179158 --video https://exemple.com/clip.mp4 --caption "Texte"

# TikTok : envoyer en BROUILLON (boîte de réception) au lieu de publier directement
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py post --draft \
    --account 2179158 --video https://exemple.com/clip.mp4 --caption "Texte"
```
Codes de sortie de `post` : `0` publié · `1` erreur · `2` TikTok a refusé · `3` timeout (encore en traitement).

## Mode brouillon / confidentialité TikTok (`share_settings`)
La doc Blog2Social expose, dans chaque entrée `b2s_posts`, un objet `share_settings` qui pilote le
mode de publication TikTok (direct vs brouillon) et la confidentialité. Le script le rend
paramétrable — **absent par défaut** (comportement réseau inchangé) :

| Flag | Effet sur `share_settings` |
|---|---|
| `--draft` | `mode: 1` → brouillon / boîte de réception (l'utilisateur finalise dans l'app TikTok) |
| `--mode N` | `mode: N` brut (0 = publication directe, 1 = brouillon). Override de `--draft` |
| `--privacy V` | `status_privacy: V` (`PUBLIC_TO_EVERYONE` *défaut*, `SELF_ONLY`, `MUTUAL_FOLLOW_FRIENDS`, `FOLLOWER_OF_CREATOR`) |
| `--allow-comment` | `allow_comment: 1` |
| `--share-settings '<json>'` | échappatoire : JSON brut, écrase tous les flags ci-dessus |

### Recette TikTok validée (test réel, publication OK ✅)
`--draft` **seul** suffit et publie. Trois enseignements confirmés en test :

1. **L'objet doit être COMPLET.** Un `share_settings` partiel (ex. `{mode:1}` seul) est rejeté :
   `b2s_error_code: NETWORK_36_SHARE_SETTINGS_MISMATCH`. Le script émet donc toujours les 5 champs.
2. **`mode:1` (brouillon) fonctionne ; `mode:0` (publication directe) est refusé** par TikTok pour
   une app non auditée → `DEFAULT`. La vidéo arrive dans la **boîte de réception TikTok**, l'utilisateur
   finalise la publication dans l'app.
3. **Confidentialité** : en brouillon, `PUBLIC_TO_EVERYONE` **et** `SELF_ONLY` sont acceptés (tous deux
   testés OK). `--draft` pré-règle donc le brouillon sur **public** par défaut (ce que veut un éditeur) ;
   `--privacy SELF_ONLY` pour un brouillon privé.

```bash
# Chemin nominal validé — publie la vidéo en brouillon TikTok (pré-réglé public)
B2S_SERVICE_TOKEN=… B2S_ACCESS_TOKEN=… python3 publish.py post --draft \
    --account 2179158 --video URL --caption "Texte #tags"
```
Objet émis par `--draft` : `{"mode":1,"status_privacy":"PUBLIC_TO_EVERYONE","allow_comment":0,`
`"promotion_option_organic":0,"promotion_option_branded":0}`.

Pour viser la **publication directe** (non-brouillon) ou une confidentialité **publique**, il faut
probablement une **app TikTok auditée + scope `video.publish`** : c'est l'objet du `SUPPORT-TICKET.md`.

## Diagnostiquer un échec TikTok
`post` interprète désormais le seul signal d'erreur exposé par l'API, `b2s_error_code`, et l'affiche
en clair en cas d'échec (`state:1`). Table d'interprétation (champ `ERROR_CODES` du script) :

| `b2s_error_code` | Sens |
|---|---|
| `TOKEN` / `LOGIN` | jeton invalide/expiré ou auth refusée → ré-authentifier |
| `RIGHT` | droits insuffisants (publication directe non autorisée sur la connexion) |
| `CONTENT` | contenu refusé (texte/média non conforme) |
| `VIDEO_NETWORK_FORMAT` | format vidéo refusé par le réseau |
| `LIMIT` / `RATE_LIMIT` | quota atteint / trop de requêtes |
| `NO_DATA` | données manquantes dans la requête |
| `DEFAULT` | erreur générique sans détail exposé (TikTok : **publication directe non autorisée** — utiliser `--draft`) |
| `NETWORK_36_SHARE_SETTINGS_MISMATCH` | `share_settings` incomplet/incohérent — émettre les 5 champs (le script le fait) |

⚠️ Limite côté API : Blog2Social **ne renvoie pas** le message humain de TikTok. `--debug` capture
tout ce que l'API expose (réponses `create` / `upload` / chaque poll `check`). Un `DEFAULT` persistant
en `mode:0` = TikTok refuse la publication directe → bascule en `--draft` (cf. recette validée ci-dessus).

## Ce que le script encapsule (découvertes validées)
- **Auth** : `/user/auth` mint un access_token (longue durée) ; la connexion réseau est liée à CE token.
- **Création** : `client_user_network_id` doit être **dans chaque `b2s_posts`** (sinon HTTP 500).
- **Vidéo** : si `video_upload_type == 1` (cas TikTok) → **upload physique en chunks** requis (l'URL seule
  ne suffit pas). Chunks divisibles par 8 ; toutes égales sauf la dernière.
- **Anti-bot** : l'hôte d'upload exige un **User-Agent navigateur** ; sinon page de challenge HTML → on
  ré-essaie chaque chunk jusqu'à réponse JSON.

## TikTok — résolu (brouillon) / limite (direct)
**Résolu** ✅ : la publication en **brouillon** fonctionne via `--draft` (recette validée ci-dessus) —
la vidéo arrive dans la boîte de réception TikTok, l'utilisateur finalise dans l'app.
**Limite** : la **publication directe** (`mode:0`) reste refusée (`DEFAULT`) pour cette connexion
(`instant_sharing: 0`, app non auditée) ; elle nécessite vraisemblablement une **app TikTok auditée +
scope `video.publish`** — question ouverte au support (`SUPPORT-TICKET.md`).
(LinkedIn/FB/Pinterest restent en auto ; Reddit en manuel.)
