import { CANONICAL_LANG, LANGS, LANG_NAMES, MIN_QUALITY, OUTPUT_DIR, type Lang } from './config.js';
import { isoDate, isoWeek, weekRange } from './week.js';
import { generateArticleBody, generateSeo, translateBody } from './anthropic.js';
import { scoreQuality } from './scorer.js';
import { writeGuide } from './writeGuide.js';
import type { Frontmatter, GeneratedSeo, GuideVersion } from './types.js';

const DRY_RUN = process.argv.includes('--dry-run');

function weekLabel(now: Date): string {
  const { start, end } = weekRange(now);
  return `du ${isoDate(start)} au ${isoDate(end)}`;
}

// ── Stubs déterministes pour --dry-run (aucun appel API) ─────────────────────
function stubBody(lang: Lang, label: string): string {
  const para = `Voici notre guide [${LANG_NAMES[lang]}] des activités à Milan pour la semaine ${label}. `.repeat(2);
  return [
    `${para}\n\nDe l'appartement EasyRest, le centre est accessible en moins de 20 minutes en métro.`,
    '## Expositions à ne pas manquer',
    'La saison apporte son lot d’expositions dans les musées et galeries du centre.\n\nPensez à réserver vos billets à l’avance, surtout le week-end.',
    '## Concerts et vie nocturne',
    'Les Navigli s’animent en soirée :',
    '- Bars à cocktails au bord de l’eau',
    '- Musique live dans les clubs du quartier',
    '- Apéritifs typiquement milanais',
    '## Autour de l’appartement',
    'Marchés de quartier, cafés et accès direct au métro composent un quotidien pratique et agréable pendant votre séjour.',
    'Profitez des rues commerçantes et des parcs à quelques minutes à pied.',
  ].join('\n\n');
}
function stubSeo(lang: Lang): GeneratedSeo {
  return {
    title: `Que faire à Milan cette semaine — ${lang.toUpperCase()}`,
    seoTitle: `Que faire à Milan cette semaine | EasyRest (${lang.toUpperCase()})`,
    description:
      'Notre sélection hebdomadaire des activités à Milan : expositions, concerts, bonnes adresses et conseils pratiques près de l’appartement EasyRest.',
    focusKeyword: 'que faire à Milan cette semaine',
    tags: ['milan', 'agenda', 'activites'],
    coverAlt: 'Vue du Duomo de Milan au coucher du soleil',
  };
}

async function buildVersion(lang: Lang, label: string, canonicalBody: string | null): Promise<GuideVersion> {
  let body: string;
  let seo: GeneratedSeo;
  if (DRY_RUN) {
    body = stubBody(lang, label);
    seo = stubSeo(lang);
  } else if (canonicalBody === null) {
    body = await generateArticleBody(lang, label); // version canonique, rédigée nativement
    seo = await generateSeo(lang, body);
  } else {
    body = await translateBody(lang, canonicalBody); // traduite depuis la canonique
    seo = await generateSeo(lang, body); // SEO natif sur le texte traduit
  }
  const { score, warnings } = scoreQuality(body, seo);
  if (warnings.length) console.log(`  [${lang}] qualité ${score} — ${warnings.join(' ')}`);
  return { lang, body, seo, quality: score };
}

async function main(): Promise<void> {
  const now = new Date();
  const { year, week } = isoWeek(now);
  const translationKey = `milan-${year}-w${week}`;
  const label = weekLabel(now);
  const pubDate = isoDate(now);

  console.log(`EasyRest engine ${DRY_RUN ? '(DRY RUN) ' : ''}— ${translationKey} (${label})`);
  console.log(`Sortie : ${OUTPUT_DIR}`);

  // 1) Version canonique
  console.log(`Génération canonique (${CANONICAL_LANG})…`);
  const canonical = await buildVersion(CANONICAL_LANG, label, null);

  // 2) Traductions des autres langues
  const others = LANGS.filter((l) => l !== CANONICAL_LANG);
  const versions: GuideVersion[] = [canonical];
  for (const lang of others) {
    console.log(`Traduction ${lang}…`);
    versions.push(await buildVersion(lang, label, canonical.body));
  }

  // 3) Écriture des 4 fichiers (reliés par translationKey ; draft si sous le seuil qualité)
  for (const v of versions) {
    // Override <title> uniquement s'il diffère réellement du H1 (sinon le site retombe sur title).
    const seoTitle = v.seo.seoTitle && v.seo.seoTitle !== v.seo.title ? v.seo.seoTitle : undefined;
    const fm: Frontmatter = {
      title: v.seo.title,
      description: v.seo.description,
      lang: v.lang,
      translationKey,
      pubDate,
      tags: v.seo.tags,
      cover: '/og-default.png', // image OG de marque par défaut ; visuel dédié par article = amélioration future
      coverAlt: v.seo.coverAlt,
      draft: v.quality < MIN_QUALITY,
      seo: { title: seoTitle, focusKeyword: v.seo.focusKeyword },
    };
    const path = await writeGuide(fm, v.body);
    console.log(`  ✓ ${v.lang} → ${path}${fm.draft ? '  (draft)' : ''}`);
  }

  console.log('Terminé. Commitez les fichiers générés pour déclencher le build.');
}

main().catch((err) => {
  console.error('Échec de la génération :', err);
  process.exit(1);
});
