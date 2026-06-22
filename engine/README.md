# EasyRest Engine

Génère **un guide hebdomadaire « Que faire à Milan »** via Claude, en 4 langues reliées
(FR canonique → EN/IT/ES traduites), et écrit des fichiers Markdown + frontmatter
directement dans la collection de contenus du site Astro (`easyrest-site/src/content/guides/`).

## Pipeline

```
semaine ISO → translationKey (milan-AAAA-wNN)
  → article canonique (FR, Claude Opus 4.8, streaming)
  → métadonnées SEO (sortie structurée)
  → score qualité (draft si < seuil)
  → traduction EN/IT/ES (+ SEO natif par langue)
  → écriture des 4 .md (frontmatter conforme au contrat) → commit → build Astro
```

## Usage

```bash
npm install
cp .env.example .env        # renseigner ANTHROPIC_API_KEY
npm run generate            # génère et écrit les 4 fichiers
npm run dry-run             # teste tout le pipeline SANS appel API ni clé
```

Variables : `ANTHROPIC_API_KEY`, `EASYREST_GUIDES_DIR` (sortie), `EASYREST_MIN_QUALITY` (défaut 70).
Modèles dans `src/config.ts` (passer `translation` à `claude-haiku-4-5` pour réduire le coût).

## Planification

Déclencher `npm run generate` une fois par semaine via **n8n** (cron, sur le Hetzner)
ou une **GitHub Action** ; le commit des fichiers déclenche le build/déploiement du site.

> Le contrat de frontmatter (champs émis ici) doit rester aligné avec
> `easyrest-site/src/content.config.ts`. Un écart fait échouer le build du site (garde-fou voulu).
