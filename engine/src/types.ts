import type { Lang } from './config.js';

// Métadonnées SEO produites par le modèle (par langue).
export interface GeneratedSeo {
  title: string; // titre SEO, < 60 caractères
  description: string; // meta description, 120–160 caractères
  focusKeyword: string;
  tags: string[]; // 3–5
  coverAlt: string; // texte alternatif de l'image de couverture
}

// Contrat de frontmatter — DOIT correspondre à easyrest-site/src/content.config.ts
export interface Frontmatter {
  title: string;
  description: string;
  lang: Lang;
  translationKey: string;
  pubDate: string; // YYYY-MM-DD
  tags: string[];
  cover?: string;
  coverAlt?: string;
  draft: boolean;
  seo?: { title?: string; focusKeyword?: string; noindex?: boolean };
}

export interface GuideVersion {
  lang: Lang;
  body: string; // Markdown (commence par un paragraphe d'intro, puis des H2)
  seo: GeneratedSeo;
  quality: number; // 0–100
}
