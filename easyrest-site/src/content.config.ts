import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

// ─────────────────────────────────────────────────────────────────────────────
// CONTRAT DE FRONTMATTER — interface entre `engine` (ce qu'il écrit) et `site`
// (ce qu'Astro attend). C'est le pivot : tout le reste s'y accroche.
// Un article = 4 fichiers (un par langue) reliés par `translationKey` → hreflang.
// Voir docs/FRONTMATTER-CONTRACT.md
// ─────────────────────────────────────────────────────────────────────────────
const guides = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/guides' }),
  schema: z.object({
    title: z.string(),
    description: z.string(), // meta description (≈ 120–160 caractères)
    lang: z.enum(['fr', 'en', 'it', 'es']),
    translationKey: z.string(), // identique sur les 4 versions d'un même article
    pubDate: z.coerce.date(),
    tags: z.array(z.string()).default([]),
    cover: z.string().optional(), // chemin de l'image de couverture
    coverAlt: z.string().optional(), // alt text (SEO + accessibilité)
    draft: z.boolean().default(false),
    // Surcharges SEO optionnelles (sinon title/description ci-dessus font foi)
    seo: z
      .object({
        title: z.string().optional(),
        focusKeyword: z.string().optional(),
        noindex: z.boolean().default(false),
      })
      .optional(),
  }),
});

export const collections = { guides };
