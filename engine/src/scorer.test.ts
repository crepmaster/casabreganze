import { test } from 'node:test';
import assert from 'node:assert/strict';
import { scoreQuality } from './scorer.js';
import type { GeneratedSeo } from './types.js';

const goodSeo: GeneratedSeo = {
  title: 'Que faire à Milan cette semaine',
  seoTitle: 'Que faire à Milan cette semaine | EasyRest',
  description:
    'Notre sélection hebdomadaire des activités à Milan : expositions, concerts, marchés et bonnes adresses près de l’appartement EasyRest pour votre séjour.',
  focusKeyword: 'que faire à Milan',
  tags: ['milan', 'agenda', 'activites'],
  coverAlt: 'Le Duomo de Milan au coucher du soleil',
};

// Corps conforme : > 1200 mots, ≥ 2 sections H2, une liste, plusieurs paragraphes.
const para = ('Milan offre chaque semaine une programmation riche et variée pour tous les goûts. ').repeat(30);
const goodBody = [
  para,
  '## Expositions à ne pas manquer',
  para,
  'Pensez à réserver vos billets à l’avance, surtout le week-end.',
  '## Concerts et vie nocturne',
  '- Bars à cocktails au bord de l’eau',
  '- Musique live dans les clubs du quartier',
  para,
  para,
].join('\n\n');

test('scoreQuality — un article conforme obtient 100 sans avertissement', () => {
  const { score, warnings } = scoreQuality(goodBody, goodSeo);
  assert.equal(score, 100);
  assert.deepEqual(warnings, []);
});

test('scoreQuality — un article trop court est fortement pénalisé', () => {
  const { score, warnings } = scoreQuality('Trop court.', goodSeo);
  assert.ok(score < 70, `score attendu < 70, obtenu ${score}`);
  assert.ok(warnings.some((w) => w.includes('court')), 'un avertissement de longueur est attendu');
});

test('scoreQuality — absence de H2 pénalisée', () => {
  const body = para + '\n\n' + para + '\n\n' + para + '\n\n' + para + '\n\n- item';
  const { warnings } = scoreQuality(body, goodSeo);
  assert.ok(warnings.some((w) => w.includes('H2')), 'un avertissement H2 est attendu');
});

test('scoreQuality — meta description hors cible pénalisée', () => {
  const seo: GeneratedSeo = { ...goodSeo, description: 'Trop courte.' };
  const { warnings } = scoreQuality(goodBody, seo);
  assert.ok(warnings.some((w) => w.includes('description')), 'un avertissement description est attendu');
});

test('scoreQuality — titre SEO trop long pénalisé', () => {
  const seo: GeneratedSeo = { ...goodSeo, seoTitle: 'x'.repeat(80) };
  const { warnings } = scoreQuality(goodBody, seo);
  assert.ok(warnings.some((w) => w.includes('Titre SEO')), 'un avertissement titre est attendu');
});
