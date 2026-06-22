import { WORD_TARGET } from './config.js';
import type { GeneratedSeo } from './types.js';

// Score qualité léger (0–100), porté du quality scorer WordPress vers une version sobre.
// Pèse : longueur, structure (H2/listes/paragraphes), SEO on-page.
export function scoreQuality(body: string, seo: GeneratedSeo): { score: number; warnings: string[] } {
  const warnings: string[] = [];
  let score = 100;

  const words = body.split(/\s+/).filter(Boolean).length;
  if (words < WORD_TARGET.min * 0.7) {
    score -= 30;
    warnings.push(`Trop court (${words} mots, cible ${WORD_TARGET.min}+).`);
  } else if (words < WORD_TARGET.min) {
    score -= 10;
    warnings.push(`Un peu court (${words} mots).`);
  }

  const h2 = (body.match(/^##\s+/gm) || []).length;
  if (h2 < 2) {
    score -= 25;
    warnings.push(`Pas assez de sections H2 (${h2}).`);
  }
  if (!/^[-*]\s+/m.test(body)) {
    score -= 10;
    warnings.push('Aucune liste à puces.');
  }
  const paragraphs = body.split(/\n{2,}/).filter((p) => p.trim().length > 0).length;
  if (paragraphs < 5) {
    score -= 10;
    warnings.push(`Peu de paragraphes (${paragraphs}).`);
  }

  // SEO on-page
  const titleLen = seo.title.length;
  if (titleLen === 0 || titleLen > 60) {
    score -= 10;
    warnings.push(`Titre SEO hors cible (${titleLen} car., viser ≤ 60).`);
  }
  const descLen = seo.description.length;
  if (descLen < 120 || descLen > 160) {
    score -= 10;
    warnings.push(`Meta description hors cible (${descLen} car., viser 120–160).`);
  }
  if (seo.tags.length < 3) {
    score -= 5;
    warnings.push('Moins de 3 tags.');
  }

  return { score: Math.max(0, score), warnings };
}
