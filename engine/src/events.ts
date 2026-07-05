import { CANONICAL_LANG, LANGS, LANG_NAMES, MIN_QUALITY, OUTPUT_DIR, type Lang } from './config.js';
import { isoDate } from './week.js';
import { generateEventsBody, generateSeo, translateBody } from './anthropic.js';
import { scoreQuality } from './scorer.js';
import { guideExists, readGuideBody, writeGuide } from './writeGuide.js';
import type { Frontmatter, GeneratedSeo, GuideVersion } from './types.js';

// Génère « l'agenda insolite de Milan » sur ~3 mois, à partir d'ÉVÉNEMENTS RÉELS
// trouvés par recherche web (outil serveur de Claude). Multilingue, écrit dans la
// collection de guides du site. À relancer périodiquement (mensuel conseillé) car
// les événements évoluent.
//
//   npm run events        → génération réelle (recherche web + Claude)
//   npm run events -- --dry-run   → pipeline sans API (stub déterministe)

const DRY_RUN = process.argv.includes('--dry-run');
const FORCE = process.argv.includes('--force');

const pad = (n: number) => String(n).padStart(2, '0');

// Étiquette de période « août 2026 → octobre 2026 » (mois courant + 2).
function periodLabel(now: Date): string {
  const fmt = new Intl.DateTimeFormat('fr', { month: 'long', year: 'numeric' });
  const end = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 2, 1));
  return `${fmt.format(now)} → ${fmt.format(end)}`;
}

// ── Stub déterministe pour --dry-run (aucun appel API / web) ─────────────────
function stubBody(label: string): string {
  return [
    `Voici notre sélection d'expériences singulières à Milan pour la période ${label}. (STUB de test — la vraie génération interroge le web.)`,
    '## Une expo confidentielle',
    'Description de l’événement, lieu et dates.\n\nDe l’appartement EasyRest, le centre est à moins de 20 minutes en métro.',
    '## Un rendez-vous insolite',
    '- Détail 1',
    '- Détail 2',
    '## Sources',
    '- https://exemple.test/evenement-1',
    '- https://exemple.test/evenement-2',
  ].join('\n\n');
}
function stubSeo(lang: Lang): GeneratedSeo {
  return {
    title: `Agenda insolite de Milan — ${lang.toUpperCase()}`,
    seoTitle: `Que faire à Milan : agenda insolite | EasyRest (${lang.toUpperCase()})`,
    description:
      'Notre sélection d’événements singuliers et méconnus à Milan sur les prochains mois : expositions confidentielles, rendez-vous insolites et bonnes adresses.',
    focusKeyword: 'agenda insolite Milan',
    tags: ['milan', 'agenda', 'insolite'],
    coverAlt: 'Ambiance nocturne insolite à Milan',
  };
}

async function buildVersion(lang: Lang, label: string, canonicalBody: string | null): Promise<GuideVersion> {
  let body: string;
  let seo: GeneratedSeo;
  if (DRY_RUN) {
    body = stubBody(label);
    seo = stubSeo(lang);
  } else if (canonicalBody === null) {
    body = await generateEventsBody(label); // agenda canonique, groundé par recherche web
    seo = await generateSeo(lang, body);
  } else {
    body = await translateBody(lang, canonicalBody);
    seo = await generateSeo(lang, body);
  }
  const { score, warnings } = scoreQuality(body, seo);
  if (warnings.length) console.log(`  [${lang}] qualité ${score} — ${warnings.join(' ')}`);
  return { lang, body, seo, quality: score };
}

async function persist(v: GuideVersion, translationKey: string, pubDate: string): Promise<void> {
  const seoTitle = v.seo.seoTitle && v.seo.seoTitle !== v.seo.title ? v.seo.seoTitle : undefined;
  const fm: Frontmatter = {
    title: v.seo.title,
    description: v.seo.description,
    lang: v.lang,
    translationKey,
    pubDate,
    tags: v.seo.tags,
    cover: '/og-default.png',
    coverAlt: v.seo.coverAlt,
    draft: v.quality < MIN_QUALITY,
    seo: { title: seoTitle, focusKeyword: v.seo.focusKeyword },
  };
  const path = await writeGuide(fm, v.body);
  console.log(`  ✓ ${v.lang} → ${path}${fm.draft ? '  (draft)' : ''}`);
}

async function alreadyDone(translationKey: string, lang: Lang): Promise<boolean> {
  if (DRY_RUN || FORCE) return false;
  return guideExists(translationKey, lang);
}

async function main(): Promise<void> {
  const now = new Date();
  const translationKey = `milan-agenda-${now.getUTCFullYear()}-${pad(now.getUTCMonth() + 1)}`;
  const label = periodLabel(now);
  const pubDate = isoDate(now);

  console.log(`EasyRest agenda ${DRY_RUN ? '(DRY RUN) ' : ''}${FORCE ? '(FORCE) ' : ''}— ${translationKey} (${label})`);
  console.log(`Sortie : ${OUTPUT_DIR}`);

  // 1) Version canonique (recherche web + rédaction).
  let canonicalBody: string;
  if (await alreadyDone(translationKey, CANONICAL_LANG)) {
    console.log(`Canonique (${CANONICAL_LANG}) déjà présente — relecture depuis le disque.`);
    canonicalBody = await readGuideBody(translationKey, CANONICAL_LANG);
  } else {
    console.log(`Recherche web + rédaction (${CANONICAL_LANG})…`);
    const canonical = await buildVersion(CANONICAL_LANG, label, null);
    await persist(canonical, translationKey, pubDate);
    canonicalBody = canonical.body;
  }

  // 2) Traductions.
  for (const lang of LANGS.filter((l) => l !== CANONICAL_LANG)) {
    if (await alreadyDone(translationKey, lang)) {
      console.log(`  ↷ ${lang} déjà présent — ignoré (--force pour régénérer).`);
      continue;
    }
    console.log(`Traduction ${lang}…`);
    const v = await buildVersion(lang, label, canonicalBody);
    await persist(v, translationKey, pubDate);
  }

  console.log('Terminé. Commitez les fichiers générés pour déclencher le build.');
}

main().catch((err) => {
  console.error('Échec de la génération de l’agenda :', err);
  process.exit(1);
});
