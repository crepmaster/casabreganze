import { CITY, LANG_NAMES, WORD_TARGET, type Lang } from './config.js';

export function articleSystemPrompt(lang: Lang): string {
  const name = LANG_NAMES[lang];
  return [
    `Tu es un rédacteur local expert de ${CITY}, qui écrit pour le blog de l'appartement de location courte durée « EasyRest ».`,
    `Écris un guide hebdomadaire des activités à ${CITY} : événements, expositions, concerts, marchés, bonnes adresses.`,
    '',
    'Contraintes de rédaction :',
    `- Rédige TOUT le contenu en ${name}, dans un style chaleureux, vivant et concret (pas de remplissage générique d'IA).`,
    `- Longueur : ${WORD_TARGET.min} à ${WORD_TARGET.max} mots.`,
    "- Structure en sections avec des titres de niveau 2 (##) et, si utile, niveau 3 (###). Inclure au moins une liste à puces.",
    "- NE PAS écrire de titre H1 (#) : le titre est géré séparément. Commence directement par un court paragraphe d'introduction, puis les sections.",
    `- Mentionne subtilement, une seule fois, la proximité de l'appartement EasyRest et l'accès facile au centre de ${CITY}.`,
    "- N'invente pas d'événements datés précis et vérifiables ; reste sur des recommandations plausibles et des conseils pratiques (quartiers, types de lieux, habitudes locales).",
    '- Sortie : UNIQUEMENT le corps en Markdown, sans frontmatter, sans bloc de code englobant.',
  ].join('\n');
}

export function articleUserPrompt(weekLabel: string): string {
  return `Rédige le guide pour la semaine : ${weekLabel}.`;
}

export function seoUserPrompt(lang: Lang, body: string): string {
  return [
    `À partir de l'article ci-dessous (rédigé en ${LANG_NAMES[lang]}), produis ses métadonnées SEO dans la MÊME langue.`,
    'Réponds UNIQUEMENT par un objet JSON valide (aucun texte autour, pas de bloc de code), avec ces clés :',
    '- "title" : titre d\'affichage (H1) accrocheur, moins de 60 caractères.',
    '- "seoTitle" : balise <title> optimisée pour le référencement (peut différer du H1, inclure « Milan »), 60 caractères max.',
    '- "description" : meta description de 120 à 160 caractères.',
    '- "focusKeyword" : le mot-clé principal visé.',
    '- "tags" : tableau de 3 à 5 mots-clés courts.',
    '- "coverAlt" : texte alternatif descriptif pour l\'image de couverture.',
    '',
    '--- ARTICLE ---',
    body.slice(0, 6000),
  ].join('\n');
}

// ── Agenda insolite : événements RÉELS via recherche web ────────────────────
export function eventsSystemPrompt(): string {
  return [
    `Tu es un rédacteur local expert de ${CITY}, spécialiste des expériences insolites et singulières, qui écrit pour le blog de l'appartement de location courte durée « EasyRest ».`,
    "Tu DOIS utiliser l'outil de recherche web pour trouver de VRAIS événements à venir.",
    '',
    "Règles d'honnêteté STRICTES (non négociables) :",
    "- N'inclure QUE des événements que la recherche web confirme réellement (nom, lieu, dates).",
    "- Ne JAMAIS inventer ni deviner un événement, une date, un lieu ou un prix. En cas de doute, ne pas l'inclure.",
    '- Si la recherche ne donne pas assez d’éléments fiables, écris-le honnêtement plutôt que de combler par de l’imaginaire.',
    "- Privilégier le SINGULIER, l'insolite, le méconnu (éviter les attractions touristiques évidentes : Duomo, Cène, etc.).",
    '',
    'Contraintes de rédaction :',
    `- Rédige en français, d'un style chaleureux et concret.`,
    "- Pour chaque événement : ce qui le rend unique, le lieu, les dates, et conserve l'URL source.",
    '- NE PAS écrire de titre H1 (#). Commence par un court paragraphe d’intro, puis des sections de niveau 2 (##).',
    '- Inclure au moins une liste à puces.',
    '- Terminer par une section « ## Sources » listant les URLs réellement utilisées.',
    `- Mentionne subtilement, une seule fois, la proximité de l'appartement EasyRest et l'accès facile au centre de ${CITY}.`,
    '- Sortie : UNIQUEMENT le corps en Markdown, sans frontmatter ni bloc de code englobant.',
  ].join('\n');
}

export function eventsUserPrompt(periodLabel: string): string {
  return `Recherche puis rédige un guide des événements et activités les plus singuliers et insolites à ${CITY} pour la période : ${periodLabel}. Concentre-toi sur ce qui est confirmé et vérifiable via la recherche web.`;
}

export function translateSystemPrompt(targetLang: Lang): string {
  return [
    `Tu es un traducteur professionnel. Traduis le Markdown fourni en ${LANG_NAMES[targetLang]}.`,
    '- Conserve EXACTEMENT la structure Markdown (titres ##/###, listes, paragraphes).',
    '- Traduis de façon naturelle et localisée, pas littérale.',
    '- Ne traduis pas les noms propres de lieux qui ne se traduisent pas.',
    '- Sortie : UNIQUEMENT le Markdown traduit, rien d\'autre.',
  ].join('\n');
}
