import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isoWeek, isoDate, weekRange } from './week.js';

const utc = (y: number, m: number, d: number) => new Date(Date.UTC(y, m - 1, d));

test('isoWeek — semaine courante de référence (ancre du run)', () => {
  // Confirmé par le dry-run : 2026-07-04 → semaine ISO 27 de 2026.
  assert.deepEqual(isoWeek(utc(2026, 7, 4)), { year: 2026, week: 27 });
});

test('isoWeek — 1er janvier appartenant à la dernière semaine de l’année précédente', () => {
  // 2021-01-01 est un vendredi → ISO 2020-W53.
  assert.deepEqual(isoWeek(utc(2021, 1, 1)), { year: 2020, week: 53 });
});

test('isoWeek — 31 décembre appartenant à la semaine 1 de l’année suivante', () => {
  // 2018-12-31 est un lundi → ISO 2019-W01.
  assert.deepEqual(isoWeek(utc(2018, 12, 31)), { year: 2019, week: 1 });
});

test('isoWeek — jeudi de la semaine 1 (premier jeudi de l’année)', () => {
  // 2026-01-01 est un jeudi → semaine 1.
  assert.deepEqual(isoWeek(utc(2026, 1, 1)), { year: 2026, week: 1 });
});

test('weekRange — lundi→dimanche de la semaine contenant la date', () => {
  const { start, end } = weekRange(utc(2026, 7, 4)); // samedi
  assert.equal(isoDate(start), '2026-06-29'); // lundi
  assert.equal(isoDate(end), '2026-07-05'); // dimanche
});

test('weekRange — un lundi reste son propre début de semaine', () => {
  const { start, end } = weekRange(utc(2026, 6, 29)); // lundi
  assert.equal(isoDate(start), '2026-06-29');
  assert.equal(isoDate(end), '2026-07-05');
});

test('isoDate — format YYYY-MM-DD', () => {
  assert.equal(isoDate(utc(2026, 7, 4)), '2026-07-04');
});
