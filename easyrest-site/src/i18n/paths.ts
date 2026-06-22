import type { CollectionEntry } from 'astro:content';
import { langList, type Lang } from './ui';

// Helpers de construction des URLs alternatives (hreflang). Source unique pour les 3 pages.

/** Pour une page « fixe » (accueil, index guides) : même chemin sous chaque préfixe de langue. */
export function indexAlternates(basePath: string): Record<Lang, string> {
  return Object.fromEntries(langList.map((l) => [l, `/${l}${basePath}`])) as Record<Lang, string>;
}

/** Pour un guide : l'URL de la version traduite si elle existe, sinon repli vers l'index des guides. */
export function guideAlternates(
  translationKey: string,
  all: CollectionEntry<'guides'>[],
): Record<Lang, string> {
  return Object.fromEntries(
    langList.map((l) => {
      const tr = all.find((e) => e.data.translationKey === translationKey && e.data.lang === l);
      return [l, tr ? `/${l}/guides/${tr.data.translationKey}/` : `/${l}/guides/`];
    }),
  ) as Record<Lang, string>;
}
