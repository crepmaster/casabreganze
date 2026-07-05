// Import des disponibilités depuis l'iCal Guesty, au BUILD (site statique).
// Le calendrier est donc INDICATIF : il reflète les dispos à la dernière génération
// (Guesty annonce un TTL d'1 h). L'agence reconfirme toujours avant encaissement.
//
// Parseur volontairement minimal, calibré sur le flux Guesty observé :
// des VEVENT « all-day » avec DTSTART/DTEND en VALUE=DATE (YYYYMMDD), DTEND exclusif
// (jour de départ = libre). Il n'ambitionne pas de gérer l'iCal générique
// (fuseaux, DATE-TIME, RRULE) — inutile pour ce flux.
import { BOOKING } from '../config';

/** Période réservée, `end` exclusif (le jour de checkout reste disponible). Bornes en YYYY-MM-DD. */
export interface BookedRange {
  start: string;
  end: string;
}

// Déplie les lignes iCal repliées (une ligne de continuation commence par un espace/tab).
function unfold(ics: string): string[] {
  const out: string[] = [];
  for (const line of ics.split(/\r?\n/)) {
    if ((line.startsWith(' ') || line.startsWith('\t')) && out.length) {
      out[out.length - 1] += line.slice(1);
    } else {
      out.push(line);
    }
  }
  return out;
}

// Extrait une date YYYY-MM-DD de la valeur d'un DTSTART/DTEND (ex. "20260705").
function toISO(value: string): string | null {
  const m = value.match(/(\d{4})(\d{2})(\d{2})/);
  return m ? `${m[1]}-${m[2]}-${m[3]}` : null;
}

export function parseBookedRanges(ics: string): BookedRange[] {
  const ranges: BookedRange[] = [];
  let inEvent = false;
  let start: string | null = null;
  let end: string | null = null;

  for (const line of unfold(ics)) {
    if (line === 'BEGIN:VEVENT') {
      inEvent = true;
      start = end = null;
    } else if (line === 'END:VEVENT') {
      if (start && end) ranges.push({ start, end });
      inEvent = false;
    } else if (inEvent && line.startsWith('DTSTART')) {
      start = toISO(line.slice(line.indexOf(':') + 1));
    } else if (inEvent && line.startsWith('DTEND')) {
      end = toISO(line.slice(line.indexOf(':') + 1));
    }
  }
  return ranges;
}

// Fetch mémoïsé : les 4 pages de langue partagent une seule requête au build.
// Renvoie `null` en cas d'échec (→ calendrier masqué) et `[]` si le flux est
// chargé mais sans aucune réservation (→ calendrier tout-libre affiché).
let _cache: Promise<BookedRange[] | null> | null = null;

export function loadBookedRanges(): Promise<BookedRange[] | null> {
  if (!BOOKING.icalUrl) return Promise.resolve(null);
  if (!_cache) {
    _cache = fetch(BOOKING.icalUrl)
      .then((r) => (r.ok ? r.text() : Promise.reject(new Error(`HTTP ${r.status}`))))
      .then(parseBookedRanges)
      .catch((err: unknown) => {
        // Dégradation propre : le build ne casse pas si Guesty est indisponible.
        const msg = err instanceof Error ? err.message : String(err);
        console.warn(`[guesty] iCal indisponible, calendrier masqué : ${msg}`);
        return null;
      });
  }
  return _cache;
}

/** Vrai si `dateISO` (YYYY-MM-DD) tombe dans une période réservée (comparaison lexicographique, end exclusif). */
export function isBooked(dateISO: string, ranges: BookedRange[]): boolean {
  return ranges.some((r) => dateISO >= r.start && dateISO < r.end);
}
