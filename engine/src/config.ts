import { fileURLToPath } from 'node:url';

// Langues — la canonique est rédigée nativement, les autres sont traduites depuis elle.
export const LANGS = ['fr', 'en', 'it', 'es'] as const;
export type Lang = (typeof LANGS)[number];
export const CANONICAL_LANG: Lang = 'fr';

export const LANG_NAMES: Record<Lang, string> = {
  fr: 'français',
  en: 'English (US)',
  it: 'italiano',
  es: 'español',
};

export const CITY = 'Milan';

// Modèles Claude. Opus 4.8 par défaut (meilleure qualité long-form multilingue).
// Pour réduire le coût des traductions, passer TRANSLATION à 'claude-haiku-4-5'.
export const MODELS = {
  article: 'claude-opus-4-8',
  seo: 'claude-opus-4-8',
  translation: 'claude-opus-4-8',
} as const;

export const WORD_TARGET = { min: 1200, max: 2000 };

export const MIN_QUALITY = Number(process.env.EASYREST_MIN_QUALITY ?? 70);

// Dossier de sortie = la collection de contenus du site Astro.
export const OUTPUT_DIR =
  process.env.EASYREST_GUIDES_DIR ??
  fileURLToPath(new URL('../../easyrest-site/src/content/guides/', import.meta.url));
