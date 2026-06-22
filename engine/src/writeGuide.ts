import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { OUTPUT_DIR } from './config.js';
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

/** Écrit un fichier `${translationKey}.${lang}.md` dans la collection de guides. Retourne le chemin. */
export async function writeGuide(fm: Frontmatter, body: string): Promise<string> {
  await mkdir(OUTPUT_DIR, { recursive: true });
  const path = join(OUTPUT_DIR, `${fm.translationKey}.${fm.lang}.md`);
  await writeFile(path, `${buildFrontmatter(fm)}\n\n${body.trim()}\n`, 'utf8');
  return path;
}
