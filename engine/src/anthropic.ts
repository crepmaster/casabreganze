import Anthropic from '@anthropic-ai/sdk';
import { MODELS, type Lang } from './config.js';
import {
  articleSystemPrompt,
  articleUserPrompt,
  eventsSystemPrompt,
  eventsUserPrompt,
  seoUserPrompt,
  translateSystemPrompt,
} from './prompts.js';
import type { GeneratedSeo } from './types.js';

// Client construit paresseusement : le mode --dry-run ne touche jamais à l'API ni à la clé.
// maxRetries relevé (défaut SDK = 2) : un cron hebdomadaire non surveillé doit encaisser
// les erreurs transitoires (429 / 5xx / coupures réseau) avec backoff exponentiel automatique.
let _client: Anthropic | null = null;
function client(): Anthropic {
  if (!_client) _client = new Anthropic({ maxRetries: 4 }); // lit ANTHROPIC_API_KEY dans l'environnement
  return _client;
}

function textOf(content: Anthropic.ContentBlock[]): string {
  return content
    .filter((b): b is Anthropic.TextBlock => b.type === 'text')
    .map((b) => b.text)
    .join('')
    .trim();
}

// Refuse une réponse inutilisable : refus de sécurité ou troncature (max_tokens).
// Mieux vaut faire échouer le run clairement que publier un contenu coupé ou vide.
function assertUsable(msg: Anthropic.Message, what: string): void {
  if (msg.stop_reason === 'refusal') {
    const cat = (msg as { stop_details?: { category?: string } }).stop_details?.category ?? 'inconnue';
    throw new Error(`${what} : le modèle a refusé la requête (catégorie : ${cat}).`);
  }
  if (msg.stop_reason === 'max_tokens') {
    throw new Error(`${what} : réponse tronquée (max_tokens atteint). Augmente max_tokens ou réduis la cible.`);
  }
}

function requireText(msg: Anthropic.Message, what: string): string {
  assertUsable(msg, what);
  const text = textOf(msg.content);
  if (!text) throw new Error(`${what} : réponse vide (aucun bloc de texte).`);
  return text;
}

// Avec l'outil web_search, le modèle émet des blocs de texte de narration ENTRE les
// recherches (« Je vais chercher… »). Le vrai article est le texte qui suit le DERNIER
// bloc lié à l'outil. On ne garde que celui-là pour ne pas polluer le corps.
function finalAnswerText(msg: Anthropic.Message, what: string): string {
  assertUsable(msg, what);
  let lastTool = -1;
  msg.content.forEach((b, i) => {
    if (b.type === 'server_tool_use' || b.type === 'web_search_tool_result') lastTool = i;
  });
  const text = msg.content
    .slice(lastTool + 1)
    .filter((b): b is Anthropic.TextBlock => b.type === 'text')
    .map((b) => b.text)
    .join('')
    .trim();
  if (!text) throw new Error(`${what} : réponse finale vide après la recherche web.`);
  return stripPreamble(text);
}

// Retire en tête : lignes vides, un éventuel titre H1 (# ...) — le titre vit dans le
// frontmatter — et une phrase méta d'annonce (« Je rédige le guide », « I now have… »)
// que le modèle ajoute parfois malgré la consigne. S'arrête au premier vrai contenu.
function stripPreamble(body: string): string {
  const meta =
    /(je (vais|vous|rédige|prépare)|j'?ai (maintenant|désormais|toutes|rassemblé|collecté|suffisamment)|voici (le|notre|un) |i (will|now|have|can)|let me|the result|here'?s the )/i;
  const lines = body.split('\n');
  let i = 0;
  while (i < lines.length) {
    const l = lines[i].trim();
    if (l === '') { i++; continue; }
    if (/^#\s+/.test(l)) { i++; continue; } // H1 → géré par le frontmatter
    if (l.length < 240 && !/^#{2,}\s/.test(l) && !/^[-*]\s/.test(l) && meta.test(l)) { i++; continue; }
    break;
  }
  return lines.slice(i).join('\n').trim();
}

/** Génère le corps de l'article (long → streaming, conformément aux bonnes pratiques SDK). */
export async function generateArticleBody(lang: Lang, weekLabel: string): Promise<string> {
  const stream = client().messages.stream({
    model: MODELS.article,
    max_tokens: 8000,
    thinking: { type: 'adaptive' },
    system: articleSystemPrompt(lang),
    messages: [{ role: 'user', content: articleUserPrompt(weekLabel) }],
  });
  return requireText(await stream.finalMessage(), `Article (${lang})`);
}

/**
 * Génère le corps de l'agenda insolite en s'appuyant sur la recherche web (outil serveur).
 * L'outil web_search tourne côté Anthropic : on relance sur `pause_turn` (limite d'itérations
 * de la boucle serveur) en réémettant l'historique, jusqu'à la réponse finale.
 */
export async function generateEventsBody(periodLabel: string): Promise<string> {
  const messages: Anthropic.MessageParam[] = [{ role: 'user', content: eventsUserPrompt(periodLabel) }];
  const MAX_CONTINUATIONS = 6;

  for (let i = 0; i < MAX_CONTINUATIONS; i++) {
    const msg = await client().messages.create({
      model: MODELS.article,
      max_tokens: 8000,
      system: eventsSystemPrompt(),
      tools: [{ type: 'web_search_20260209', name: 'web_search' }],
      messages,
    });

    // La boucle d'outils serveur a atteint sa limite : réémettre pour continuer.
    if (msg.stop_reason === 'pause_turn') {
      messages.push({ role: 'assistant', content: msg.content });
      continue;
    }
    return finalAnswerText(msg, 'Agenda (fr)');
  }
  throw new Error(`Agenda (fr) : recherche web non conclue après ${MAX_CONTINUATIONS} relances (pause_turn).`);
}

/** Traduit le corps Markdown depuis la langue canonique vers `targetLang`. */
export async function translateBody(targetLang: Lang, body: string): Promise<string> {
  const stream = client().messages.stream({
    model: MODELS.translation,
    max_tokens: 8000,
    system: translateSystemPrompt(targetLang),
    messages: [{ role: 'user', content: body }],
  });
  return requireText(await stream.finalMessage(), `Traduction (${targetLang})`);
}

// Extrait robustement un objet JSON d'une réponse texte (tolère ```json fences et préambule).
function parseJsonObject(text: string, what: string): unknown {
  let t = text.trim().replace(/^```(?:json)?/i, '').replace(/```$/, '').trim();
  const start = t.indexOf('{');
  const end = t.lastIndexOf('}');
  if (start === -1 || end === -1 || end < start) {
    throw new Error(`${what} : aucun objet JSON dans la réponse.`);
  }
  try {
    return JSON.parse(t.slice(start, end + 1));
  } catch (e) {
    throw new Error(`${what} : JSON invalide (${(e as Error).message}).`);
  }
}

/**
 * Métadonnées SEO par langue. Approche « JSON sur demande » (portable sur toute version du SDK,
 * sans dépendre de output_config) avec extraction et validation robustes.
 */
export async function generateSeo(lang: Lang, body: string): Promise<GeneratedSeo> {
  const msg = await client().messages.create({
    model: MODELS.seo,
    max_tokens: 1024,
    messages: [{ role: 'user', content: seoUserPrompt(lang, body) }],
  });
  const raw = parseJsonObject(requireText(msg, `SEO (${lang})`), `SEO (${lang})`) as Partial<GeneratedSeo>;

  if (
    typeof raw.title !== 'string' ||
    typeof raw.description !== 'string' ||
    typeof raw.focusKeyword !== 'string' ||
    typeof raw.coverAlt !== 'string' ||
    !Array.isArray(raw.tags)
  ) {
    throw new Error(`SEO (${lang}) : champs manquants ou de type incorrect.`);
  }
  return {
    title: raw.title,
    seoTitle: typeof raw.seoTitle === 'string' ? raw.seoTitle : undefined,
    description: raw.description,
    focusKeyword: raw.focusKeyword,
    coverAlt: raw.coverAlt,
    tags: raw.tags.map(String),
  };
}
