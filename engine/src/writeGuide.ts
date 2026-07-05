import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { OUTPUT_DIR, type Lang } from './config.js';
import type { Frontmatter } from './types.js';

// Sérialisation YAML minimale et sûre : on cite en JSON les chaînes (scalaire YAML valide),
// et on émet les tableaux en séquence de flux JSON (également valide en YAML).
function yamlValue(v: unknown): string {
  if (Array.isArray(v)) return `[${v.map((x) => JSON.stringify(x)).join(', ')}]`;
  if (typeof v === 'boolean' || typeof v === 'number') return String(v);
  return JSON.stringify(String(v));
}

export function buildFrontmatter(fm: Frontmatter): string {
  const lines: string[] = ['---'];
  lines.push(`title: ${yamlValue(fm.title)}`);
  lines.push(`description: ${yamlValue(fm.description)}`);
  lines.push(`lang: ${fm.lang}`);
  lines.push(`translationKey: ${yamlValue(fm.translationKey)}`);
  lines.push(`pubDate: ${fm.pubDate}`);
  lines.push(`tags: ${yamlValue(fm.tags)}`);
  if (fm.cover) lines.push(`cover: ${yamlValue(fm.cover)}`);
  if (fm.coverAlt) lines.push(`coverAlt: ${yamlValue(fm.coverAlt)}`);
  lines.push(`draft: ${fm.draft}`);
  if (fm.seo) {
    lines.push('seo:');
    if (fm.seo.title) lines.push(`  title: ${yamlValue(fm.seo.title)}`);
    if (fm.seo.focusKeyword) lines.push(`  focusKeyword: ${yamlValue(fm.seo.focusKeyword)}`);
    if (fm.seo.noindex) lines.push(`  noindex: ${fm.seo.noindex}`);
  }
  lines.push('---');
  return lines.join('\n');
}

function guidePath(translationKey: string, lang: Lang): string {
  return join(OUTPUT_DIR, `${translationKey}.${lang}.md`);
}

/** Écrit un fichier `${translationKey}.${lang}.md` dans la collection de guides. Retourne le chemin. */
export async function writeGuide(fm: Frontmatter, body: string): Promise<string> {
  await mkdir(OUTPUT_DIR, { recursive: true });
  const path = guidePath(fm.translationKey, fm.lang);
  await writeFile(path, `${buildFrontmatter(fm)}\n\n${body.trim()}\n`, 'utf8');
  return path;
}

/** Vrai si le guide de cette langue existe déjà (checkpoint de reprise). */
export async function guideExists(translationKey: string, lang: Lang): Promise<boolean> {
  try {
    await readFile(guidePath(translationKey, lang), 'utf8');
    return true;
  } catch {
    return false;
  }
}

/**
 * Relit le corps Markdown d'un guide déjà écrit (sans le frontmatter).
 * Utilisé à la reprise : si la canonique existe mais qu'une traduction manque,
 * on récupère le corps FR depuis le disque plutôt que de le régénérer.
 * Le frontmatter est délimité par deux lignes `---` ; les valeurs sont
 * échappées en JSON, donc aucune ligne `---` ne peut apparaître à l'intérieur.
 */
export async function readGuideBody(translationKey: string, lang: Lang): Promise<string> {
  const raw = await readFile(guidePath(translationKey, lang), 'utf8');
  const parts = raw.split(/^---\s*$/m);
  if (parts.length < 3) throw new Error(`Guide illisible (frontmatter absent) : ${guidePath(translationKey, lang)}`);
  return parts.slice(2).join('---').trim();
}
