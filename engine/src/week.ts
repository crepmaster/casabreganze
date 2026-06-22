// Calculs en UTC de bout en bout (lecture des composants getUTC*, sortie toISOString).
// Volontaire : pour un cron hebdomadaire, l'éventuel décalage de date au minuit local est sans effet.
// Numéro de semaine ISO-8601 (lundi = premier jour, semaine 1 = celle du premier jeudi).
export function isoWeek(date: Date): { year: number; week: number } {
  const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
  const day = d.getUTCDay() || 7; // dimanche = 7
  d.setUTCDate(d.getUTCDate() + 4 - day); // jeudi de la semaine courante
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  const week = Math.ceil(((d.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
  return { year: d.getUTCFullYear(), week };
}

export function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

// Lundi → dimanche de la semaine contenant `date`.
export function weekRange(date: Date): { start: Date; end: Date } {
  const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
  const day = d.getUTCDay() || 7;
  const monday = new Date(d);
  monday.setUTCDate(d.getUTCDate() - (day - 1));
  const sunday = new Date(monday);
  sunday.setUTCDate(monday.getUTCDate() + 6);
  return { start: monday, end: sunday };
}
